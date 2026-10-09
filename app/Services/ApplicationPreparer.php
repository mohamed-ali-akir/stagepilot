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

/**
 * Prepares the next applications of a student: finds the website and the email of each company,
 * asks Claude for a hook, and only keeps hooks whose proof is found on the company's site.
 * Never sends anything.
 */
class ApplicationPreparer
{
    public function __construct(
        private CompanySearch $search,
        private WebsiteFinder $websites,
        private EmailFinder $emails,
        private SiteText $siteText,
        private HookGenerator $generator,
        private HookVerifier $verifier,
    ) {}

    /**
     * @return array{searched: int, prepared: int, ignored: int, failed: int}
     */
    public function prepare(User $user, ?int $batchSize = null): array
    {
        $batchSize ??= config('stagepilot.prepare_batch_size');
        $stats = ['searched' => 0, 'prepared' => 0, 'ignored' => 0, 'failed' => 0];

        if ($this->candidates($user)->doesntExist()) {
            $stats['searched'] = $this->search->searchFor($user);
        }

        foreach ($this->candidates($user)->limit($batchSize)->get() as $application) {
            $result = $this->prepareOne($user, $application);
            $stats[$result]++;
        }

        ActivityLog::record($user, 'prepare', "{$stats['prepared']} accroche(s) prête(s), {$stats['ignored']} entreprise(s) écartée(s), {$stats['failed']} à reprendre à la main");

        return $stats;
    }

    /** Applications still to prepare: to review, no hook yet, no previous failure. Best first. */
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

        // the free engines (template, local AI, Groq) are less reliable: their hooks always wait for the student
        $auto = $user->profile->validation_mode === ValidationMode::AutoWithVeto
            && ! $this->generator instanceof TemplateHookGenerator
            && ! $this->generator instanceof OllamaHookGenerator
            && ! $this->generator instanceof GroqHookGenerator;
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

    private function ignore(JobApplication $application, string $reason): string
    {
        $application->status = ApplicationStatus::Ignored;
        $application->addNote($reason);
        $application->save();

        return 'ignored';
    }
}
