<?php

namespace App\Livewire;

use App\Enums\ApplicationStatus;
use App\Jobs\PrepareApplicationsJob;
use App\Models\ActivityLog;
use App\Services\ApplicationSender;
use App\Services\EmailComposer;
use App\Services\Mail\Mailbox;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Tableau de bord')]
class Dashboard extends Component
{
    public bool $sendingEnabled = false;

    public function mount(): void
    {
        $this->sendingEnabled = ! Auth::user()->profile->paused;
    }

    #[Computed]
    public function counts(): array
    {
        $applications = Auth::user()->jobApplications();
        $count = fn (ApplicationStatus ...$s) => (clone $applications)->withStatus(...$s)->count();

        return [
            'review' => $count(ApplicationStatus::PendingReview),
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

    #[Computed]
    public function activity()
    {
        return ActivityLog::where('user_id', Auth::id())->with('jobApplication.company')->latest()->limit(12)->get();
    }

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
            Flux::toast(variant: 'danger', text: 'Ajoute d\'abord ton mot de passe d\'application Gmail dans ton profil.');

            return;
        }
        Auth::user()->profile->update(['paused' => ! $enabled]);
        ActivityLog::record(Auth::user(), $enabled ? 'sending_enabled' : 'sending_paused');
        Flux::toast(text: $enabled ? 'Envois activés : ils partiront à 9h les jours ouvrés.' : 'Envois en pause.');
    }

    public function prepare(): void
    {
        PrepareApplicationsJob::dispatch(Auth::id(), 5);
        ActivityLog::record(Auth::user(), 'prepare_requested', '5 entreprises');
        Flux::toast(text: 'Préparation lancée en arrière-plan (recherche, sites, emails, accroches). Les résultats arrivent dans « À valider ».');
    }

    public function internshipFound(): void
    {
        Auth::user()->profile->update(['internship_found_at' => now(), 'paused' => true]);
        $this->sendingEnabled = false;
        ActivityLog::record(Auth::user(), 'internship_found');
        Flux::toast(variant: 'success', text: 'Félicitations ! Plus aucun mail ne partira.');
    }

    /** Sends one example application to the student, to check the result in Gmail. Not recorded as sent. */
    public function sendTest(Mailbox $mailbox, EmailComposer $composer): void
    {
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
}
