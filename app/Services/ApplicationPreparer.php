<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\ValidationMode;
use App\Models\ActivityLog;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\Hooks\GroqHookGenerator;
use App\Services\Hooks\HookGenerator;
use App\Services\Hooks\HookProposal;
use App\Services\Hooks\HookVerifier;
use App\Services\Hooks\OllamaHookGenerator;
use App\Services\Hooks\SiteText;
use App\Services\Hooks\TemplateHookGenerator;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Prepares the next applications of a student: finds the website and the email of each company,
 * asks Claude for a hook, and only keeps hooks whose proof is found on the company's site.
 * Never sends anything.
 */
class ApplicationPreparer
{
    /**
     * The free engines (template, local AI, Groq) are less reliable: their hooks always wait for the student.
     * Also used by the questionnaire, which greys out the automatic mode when it would not apply.
     */
    public static function allowsAutomaticValidation(HookGenerator $generator): bool
    {
        return ! $generator instanceof TemplateHookGenerator
            && ! $generator instanceof OllamaHookGenerator
            && ! $generator instanceof GroqHookGenerator;
    }

    public function __construct(
        private CompanySearch $search,
        private WebsiteFinder $websites,
        private EmailFinder $emails,
        private SiteText $siteText,
        private HookGenerator $generator,
        private HookVerifier $verifier,
    ) {}

    /** Hooks the student can still have written today (`prepare_daily_limit` a day). */
    public static function remainingToday(User $user): int
    {
        $writtenToday = ActivityLog::where('user_id', $user->id)
            ->where('action', 'hook_written')
            ->where('created_at', '>=', today())
            ->count();

        return max(0, config()->integer('stagepilot.prepare_daily_limit') - $writtenToday);
    }

    /**
     * Prepares companies one by one until $wanted hooks are written: a discarded company (no website, no email...)
     * does not count, the next one is tried. Stops at the daily limit, after `prepare_max_companies` companies,
     * or when the search finds nothing new.
     *
     * @return array{searched: int, prepared: int, ignored: int, failed: int}
     */
    public function prepare(User $user, ?int $wanted = null): array
    {
        $wanted = min($wanted ?? config('stagepilot.prepare_batch_size'), self::remainingToday($user));
        $stats = ['searched' => 0, 'prepared' => 0, 'ignored' => 0, 'failed' => 0];
        $tried = 0;
        $searched = false;

        while ($stats['prepared'] < $wanted && $tried < config('stagepilot.prepare_max_companies')) {
            $application = $this->candidates($user)->first();
            if (! $application) {
                // no company left to try: one new search, then stop if it finds nothing new
                if ($searched || ! ($stats['searched'] = $this->search->searchFor($user))) {
                    break;
                }
                $searched = true;

                continue;
            }
            // each outcome takes the application out of the candidates (hook, hook_error or ignored)
            $stats[$this->prepareOne($user, $application)]++;
            $tried++;
        }

        $limit = self::remainingToday($user) === 0 ? ' (limite de '.config('stagepilot.prepare_daily_limit').' par jour atteinte)' : '';
        ActivityLog::record($user, 'prepare', "{$stats['prepared']} accroche(s) prête(s), {$stats['ignored']} entreprise(s) écartée(s), {$stats['failed']} à reprendre à la main{$limit}");

        return $stats;
    }

    /**
     * Applications still to prepare: to review, no hook yet, no previous failure. Best first.
     *
     * @return HasMany<JobApplication, User>
     */
    private function candidates(User $user)
    {
        return $user->jobApplications()
            ->withStatus(ApplicationStatus::PendingReview)
            ->whereNull('hook')
            ->whereNull('hook_error')
            ->whereHas('company', fn ($q) => $q->where('blocked', false))
            ->orderBy('priority')
            ->orderBy('distance_km');
    }

    /**
     * @return 'prepared'|'ignored'|'failed'
     */
    public function prepareOne(User $user, JobApplication $application): string
    {
        $company = $application->company;

        if (! $company->website_searched) {
            if ($found = $this->websites->find($company)) {
                $company->website = $found['url'];
                $company->website_proof = $found['proof'];
            }
            $company->website_searched = true;
            $company->save();
        }
        if (! $company->website) {
            return $this->ignore($application, 'aucun site web trouvé');
        }

        if (! $company->email_searched) {
            $found = $this->emails->find($company->website, $company->name);
            $company->email = $found['email'];
            $company->phone ??= $found['phone'];
            $company->email_note = $found['notes'] ? implode(', ', $found['notes']) : null;
            $company->email_searched = true;
            $company->verified_at = now();
            $company->save();
        }
        if (! $company->email) {
            return $this->ignore($application, 'aucun email trouvé'.($company->phone ? ' : à appeler' : ''));
        }

        $proposal = $this->generator->propose($user, $company, $this->siteText->collect($company->website));
        if (! $proposal instanceof HookProposal) {
            $application->update(['hook_error' => mb_substr($proposal, 0, 250)]);

            return 'failed';
        }
        if (! $proposal->relevant) {
            return $this->ignore($application, 'hors sujet : '.$proposal->reason);
        }

        $problems = $this->verifier->check($company, $proposal->hook, $proposal->excerpt, $proposal->url);
        if ($problems) {
            $application->update(['hook_error' => mb_substr(implode(' ; ', $problems), 0, 250)]);
            ActivityLog::record($user, 'hook_rejected', implode(' ; ', $problems), $application);

            return 'failed';
        }

        $auto = $user->profile->validation_mode === ValidationMode::AutoWithVeto && self::allowsAutomaticValidation($this->generator);
        $application->fill([
            'hook' => $proposal->hook,
            'proof_excerpt' => $proposal->excerpt,
            'proof_url' => $proposal->url,
            'hook_generated' => true,
            'hook_error' => null,
            // auto mode: ready, but never sent the same day (one night to cancel)
            'status' => $auto ? ApplicationStatus::Ready : ApplicationStatus::PendingReview,
        ]);
        $application->save();
        ActivityLog::record($user, 'hook_written', $proposal->hook, $application);

        return 'prepared';
    }

    /** @return 'ignored' */
    private function ignore(JobApplication $application, string $reason): string
    {
        $application->status = ApplicationStatus::Ignored;
        $application->addNote($reason);
        $application->save();

        return 'ignored';
    }
}
