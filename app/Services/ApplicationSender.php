<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\SentEmailType;
use App\Jobs\SendApplicationJob;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\SentEmail;
use App\Models\User;
use App\Services\Hooks\HookRules;
use App\Services\Mail\Mailbox;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sends applications and follow-ups from the student's Gmail, with every safeguard of the prototype:
 * daily quota, no weekend / holiday, pause, stop once the internship is found, one email per company,
 * one follow-up only, hooks re-checked right before sending, one night to cancel automatic hooks.
 */
class ApplicationSender
{
    public function __construct(private Mailbox $mailbox, private EmailComposer $composer, private HookRules $rules) {}

    /** null = sending allowed today, otherwise the reason why not. */
    public function blockedReason(User $user): ?string
    {
        $profile = $user->profile;
        $today = now();

        return match (true) {
            $profile === null => 'questionnaire pas terminé',
            $profile->internship_found_at !== null || $user->jobApplications()->withStatus(ApplicationStatus::InternshipSecured)->exists() => 'stage trouvé : plus aucun envoi',
            $profile->paused => 'envois en pause',
            ! $user->canSendEmails() => 'mot de passe d\'application Gmail manquant',
            $today->isWeekend() => 'pas d\'envoi le week-end',
            in_array($today->toDateString(), config('stagepilot.holidays'), true) => 'jour férié',
            $today->greaterThan($profile->lastSendingDay()) => 'trop près du début du stage',
            default => null,
        };
    }

    public function remainingQuota(User $user): int
    {
        $quota = min((int) $user->profile->daily_quota, (int) config('stagepilot.max_daily_quota'));
        $sentToday = SentEmail::whereIn('job_application_id', $user->jobApplications()->select('id'))
            ->where('sent_at', '>=', now()->startOfDay())
            ->count();

        return max(0, $quota - $sentToday);
    }

    /**
     * What should be sent now: follow-ups first, then ready applications (priority A first).
     *
     * @return Collection<int, array{type: SentEmailType, application: JobApplication}>
     */
    public function dueTasks(User $user): Collection
    {
        $followUps = $user->jobApplications()
            ->with('company')
            ->withStatus(ApplicationStatus::Sent)
            ->where('sent_at', '<=', now()->subDays(config('stagepilot.follow_up_after_days')))
            ->whereDoesntHave('sentEmails', fn ($q) => $q->where('type', SentEmailType::FollowUp->value))
            ->orderBy('sent_at')
            ->get()
            ->map(fn ($a) => ['type' => SentEmailType::FollowUp, 'application' => $a]);

        $applications = $user->jobApplications()
            ->with('company')
            ->withStatus(ApplicationStatus::Ready)
            ->orderBy('priority')
            ->orderBy('distance_km')
            ->get()
            ->filter(fn ($a) => $this->canSendApplication($a) === null)
            ->map(fn ($a) => ['type' => SentEmailType::Application, 'application' => $a]);

        return $followUps->concat($applications)->filter(fn ($t) => ! $t['application']->company->blocked)->values();
    }

    /** null = this application can be sent, otherwise why not. */
    public function canSendApplication(JobApplication $application): ?string
    {
        $company = $application->company;

        return match (true) {
            blank($company->email) => 'pas d\'email',
            $company->blocked => 'entreprise bloquée',
            ! $this->rules->isValid($application->hook, $company->name) => 'accroche hors règles : '.implode(', ', $this->rules->problems($application->hook, $company->name)),
            // automatic hook (no manual approval): never sent the day it was written
            $application->approved_at === null && $application->updated_at->isToday() => 'accroche écrite aujourd\'hui : envoi demain',
            $company->applicationsSentThisMonth() >= Company::MONTHLY_APPLICATION_LIMIT => 'déjà 2 candidatures ce mois-ci pour cette entreprise',
            default => null,
        };
    }

    /**
     * Queues today's emails, spaced out by a random pause (1 to 3 minutes).
     *
     * @return int number of emails queued
     */
    public function scheduleToday(User $user): int
    {
        if ($reason = $this->blockedReason($user)) {
            ActivityLog::record($user, 'sending_skipped', $reason);

            return 0;
        }

        $tasks = $this->dueTasks($user)->take($this->remainingQuota($user));
        [$min, $max] = config('stagepilot.pause_between_emails');
        $delay = 0;
        foreach ($tasks as $task) {
            SendApplicationJob::dispatch($task['application']->id, $task['type'])->delay(now()->addSeconds($delay));
            $delay += random_int($min, $max);
        }

        return $tasks->count();
    }

    /**
     * Sends one email now, after checking everything again (the job may run minutes later).
     *
     * @return string|null error, or null if sent
     */
    public function send(JobApplication $application, SentEmailType $type): ?string
    {
        $user = $application->user;
        $application->loadMissing('company');

        if ($reason = $this->blockedReason($user)) {
            return $reason;
        }
        if ($this->remainingQuota($user) <= 0) {
            return 'quota du jour atteint';
        }
        if ($application->sentEmails()->where('type', $type->value)->exists()) {
            return 'déjà envoyé';
        }
        if ($type === SentEmailType::Application) {
            if ($application->status !== ApplicationStatus::Ready) {
                return 'candidature plus prête ('.$application->status->label().')';
            }
            if ($reason = $this->canSendApplication($application)) {
                return $reason;
            }
            $email = $this->composer->application($user, $application);
        } else {
            if ($application->status !== ApplicationStatus::Sent) {
                return 'relance inutile ('.$application->status->label().')';
            }
            $email = $this->composer->followUp($user, $application);
        }

        $messageId = '<'.Str::uuid().'@'.Str::after($user->email, '@').'>';
        $cv = $user->profile->cv_path ? Storage::disk('local')->path($user->profile->cv_path) : null;

        try {
            $this->mailbox->send($user, $application->company->email, $email['subject'], $email['body'], $cv, $messageId);
        } catch (\Throwable $e) {
            $application->addNote('envoi refusé : '.mb_substr($e->getMessage(), 0, 120));
            $application->save();
            ActivityLog::record($user, 'send_failed', $e->getMessage(), $application);

            return 'erreur Gmail : '.$e->getMessage();
        }

        DB::transaction(function () use ($application, $type, $messageId) {
            $application->sentEmails()->create([
                'type' => $type,
                'recipient' => $application->company->email,
                'message_id' => $messageId,
                'sent_at' => now(),
            ]);
            if ($type === SentEmailType::Application) {
                $application->update(['status' => ApplicationStatus::Sent, 'sent_at' => now()]);
            } else {
                $application->update(['status' => ApplicationStatus::FollowedUp, 'followed_up_at' => now()]);
            }
        });
        ActivityLog::record($user, $type === SentEmailType::Application ? 'application_sent' : 'follow_up_sent', $application->company->email, $application);

        return null;
    }
}
