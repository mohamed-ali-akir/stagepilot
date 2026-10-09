<?php

namespace App\Livewire;

use App\Enums\ApplicationStatus;
use App\Models\ActivityLog;
use App\Models\JobApplication;
use App\Services\Hooks\HookRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Hooks waiting for the student: approve, edit or discard each one.
 */
#[Title('À valider')]
class Review extends Component
{
    /** Hooks being edited, by application id. */
    public array $drafts = [];

    #[Computed]
    public function applications()
    {
        return Auth::user()->jobApplications()
            ->withStatus(ApplicationStatus::PendingReview)
            ->where(fn ($q) => $q->whereNotNull('hook')->orWhereNotNull('hook_error'))
            ->with('company')
            ->orderByRaw('hook is null')
            ->orderBy('priority')
            ->orderBy('distance_km')
            ->limit(30)
            ->get();
    }

    #[Computed]
    public function waitingPreparation(): int
    {
        return Auth::user()->jobApplications()->withStatus(ApplicationStatus::PendingReview)->whereNull('hook')->whereNull('hook_error')->count();
    }

    public function edit(int $id): void
    {
        $this->drafts[$id] = (string) $this->find($id)->hook;
    }

    public function approve(int $id, HookRules $rules): void
    {
        $application = $this->find($id);
        $hook = trim($this->drafts[$id] ?? (string) $application->hook);

        if ($problems = $rules->problems($hook, $application->company->name)) {
            $this->addError("drafts.$id", 'À corriger : '.implode(', ', $problems).'.');

            return;
        }
        if (! $application->company->email) {
            $this->addError("drafts.$id", 'Pas d\'email pour cette entreprise.');

            return;
        }

        $edited = $hook !== (string) $application->hook;
        $application->update([
            'hook' => $hook,
            'status' => ApplicationStatus::Ready,
            'approved_at' => now(),
            'hook_error' => null,
            // a hook rewritten by the student no longer relies on the AI's proof
            'hook_generated' => $application->hook_generated && ! $edited,
        ]);
        unset($this->drafts[$id]);
        ActivityLog::record(Auth::user(), 'hook_approved', $hook, $application);
        Flux::toast(variant: 'success', text: $application->company->name.' : prête à partir.');
    }

    public function discard(int $id): void
    {
        $application = $this->find($id);
        $application->status = ApplicationStatus::Ignored;
        $application->addNote('écartée par toi');
        $application->save();
        unset($this->drafts[$id]);
        Flux::toast(text: $application->company->name.' écartée.');
    }

    private function find(int $id): JobApplication
    {
        return Auth::user()->jobApplications()->with('company')->findOrFail($id);
    }
}
