<?php

namespace App\Livewire;

use App\Enums\ApplicationStatus;
use App\Jobs\PrepareApplicationsJob;
use App\Models\ActivityLog;
use App\Models\JobApplication;
use App\Services\ApplicationPreparer;
use App\Services\ApplicationSender;
use App\Services\EmailComposer;
use App\Services\Mail\Mailbox;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read bool $preparing
 * @property-read int $remainingToday
 */
#[Title('Tableau de bord')]
class Dashboard extends Component
{
    private const PREPARE_CHOICES = [5, 10, 20];

    private const DEMO_MESSAGE = 'Dans la démo, aucun mail n\'est envoyé et rien n\'est recherché : les données sont fictives.';

    public bool $sendingEnabled = false;

    public function mount(): void
    {
        $this->sendingEnabled = ! Auth::user()->profile->paused;
    }

    /** @return array<string, int> */
    #[Computed]
    public function counts(): array
    {
        $applications = Auth::user()->jobApplications();
        $count = fn (ApplicationStatus ...$s) => (clone $applications)->withStatus(...$s)->count();

        return [
            'review' => (clone $applications)->withStatus(ApplicationStatus::PendingReview)
                ->where(fn ($q) => $q->whereNotNull('hook')->orWhereNotNull('hook_error'))->count(),
            'to_prepare' => (clone $applications)->withStatus(ApplicationStatus::PendingReview)
                ->whereNull('hook')->whereNull('hook_error')->count(),
            'ready' => $count(ApplicationStatus::Ready),
            'waiting' => $count(...ApplicationStatus::awaitingReply()),
            'replies' => $count(ApplicationStatus::ReplyReceived),
            'interviews' => $count(ApplicationStatus::Interview, ApplicationStatus::Positive),
            'total' => (clone $applications)->count(),
        ];
    }

    #[Computed]
    public function blockedReason(): ?string
    {
        return app(ApplicationSender::class)->blockedReason(Auth::user()->load('profile'));
    }

    /** @return Collection<int, ActivityLog> */
    #[Computed]
    public function activity()
    {
        return ActivityLog::where('user_id', Auth::id())->with('jobApplication.company')->latest()->limit(12)->get();
    }

    /** @return Collection<int, JobApplication> */
    #[Computed]
    public function toCall()
    {
        return Auth::user()->jobApplications()
            ->withStatus(ApplicationStatus::Ignored)
            ->where('notes', 'like', '%à appeler%')
            ->with('company')
            ->orderBy('priority')
            ->limit(8)
            ->get();
    }

    public function updatedSendingEnabled(bool $enabled): void
    {
        if ($enabled && ! Auth::user()->canSendEmails()) {
            $this->sendingEnabled = false;
            Flux::toast(variant: 'danger', text: Auth::user()->is_demo
                ? self::DEMO_MESSAGE
                : 'Ajoute d\'abord ton mot de passe d\'application Gmail dans ton profil.');

            return;
        }
        Auth::user()->profile->update(['paused' => ! $enabled]);
        ActivityLog::record(Auth::user(), $enabled ? 'sending_enabled' : 'sending_paused');
        Flux::toast(text: $enabled ? 'Envois activés : ils partiront à 9h les jours ouvrés.' : 'Envois en pause.');
    }

    /** A preparation was asked and has not finished yet (after an hour, it is considered lost). */
    #[Computed]
    public function preparing(): bool
    {
        $logs = ActivityLog::where('user_id', Auth::id());
        // ids rather than dates: two log lines can share the same second
        $requested = (clone $logs)->where('action', 'prepare_requested')->where('created_at', '>=', now()->subHour())->max('id');
        $finished = (clone $logs)->where('action', 'prepare')->max('id');

        return $requested !== null && ($finished === null || $requested > $finished);
    }

    #[Computed]
    public function remainingToday(): int
    {
        return ApplicationPreparer::remainingToday(Auth::user());
    }

    /**
     * Choices offered by the "Préparer" button: 5, 10, 20, never more than what is left today.
     *
     * @return list<int>
     */
    #[Computed]
    public function prepareChoices(): array
    {
        $left = $this->remainingToday;

        $choices = array_filter(self::PREPARE_CHOICES, fn ($n) => $n < $left);
        if ($left > 0) {
            $choices[] = $left;
        }

        return array_values(array_unique($choices));
    }

    public function prepare(int $count = 5): void
    {
        if ($this->refuseInDemo()) {
            return;
        }
        if ($this->preparing) {
            Flux::toast(text: 'Une préparation est déjà en cours : le résultat arrive dans « À valider ».');

            return;
        }
        $count = min(max($count, 1), $this->remainingToday);
        if ($count === 0) {
            Flux::toast(variant: 'danger', text: 'Limite de '.config('stagepilot.prepare_daily_limit').' accroches par jour atteinte : la suite demain.');

            return;
        }
        // logged before the dispatch, so that a synchronous queue (local) logs the end after it
        ActivityLog::record(Auth::user(), 'prepare_requested', "{$count} accroche(s) demandée(s)");
        PrepareApplicationsJob::dispatch((int) Auth::id(), $count);
        unset($this->preparing);
        Flux::toast(text: "Préparation de {$count} accroche(s) lancée en arrière-plan. Compte jusqu'à 15 minutes : les entreprises sans site ou sans email sont passées, les accroches arrivent dans « À valider ».");
    }

    public function internshipFound(): void
    {
        if ($this->refuseInDemo()) {
            return;
        }
        Auth::user()->profile->update(['internship_found_at' => now(), 'paused' => true]);
        $this->sendingEnabled = false;
        ActivityLog::record(Auth::user(), 'internship_found');
        Flux::toast(variant: 'success', text: 'Félicitations ! Plus aucun mail ne partira.');
    }

    /** Sends one example application to the student, to check the result in Gmail. Not recorded as sent. */
    public function sendTest(Mailbox $mailbox, EmailComposer $composer): void
    {
        if ($this->refuseInDemo()) {
            return;
        }
        $user = Auth::user()->load('profile.program');
        $application = $user->jobApplications()->whereNotNull('hook')->with('company')->latest('updated_at')->first();
        if (! $user->canSendEmails() || ! $application) {
            Flux::toast(variant: 'danger', text: 'Il faut un mot de passe Gmail et au moins une accroche écrite.');

            return;
        }

        $email = $composer->application($user, $application);
        $cv = $user->profile->cv_path ? Storage::disk('local')->path($user->profile->cv_path) : null;
        try {
            $mailbox->send($user, $user->email, '[TEST] '.$email['subject'], $email['body'], $cv, '<test-'.Str::uuid().'@'.Str::after($user->email, '@').'>');
            Flux::toast(variant: 'success', text: 'Mail de test envoyé à '.$user->email.' (exemple : '.$application->company->name.').');
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: 'Gmail a refusé : '.Str::limit($e->getMessage(), 120));
        }
    }

    /** Shared demo account: no search, no email, no change that would spoil it for the next visitor. */
    private function refuseInDemo(): bool
    {
        if (Auth::user()->is_demo) {
            Flux::toast(variant: 'warning', text: self::DEMO_MESSAGE);
        }

        return Auth::user()->is_demo;
    }
}
