<?php

namespace App\Livewire;

use App\Enums\ApplicationStatus;
use App\Models\ActivityLog;
use App\Models\JobApplication;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Follow-up table (replaces the Excel sheet of the prototype).
 */
#[Title('Candidatures')]
class Applications extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $search = '';

    public ?int $selectedId = null;

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function applications()
    {
        $statuses = match ($this->status) {
            'sent' => ApplicationStatus::awaitingReply(),
            'interview' => [ApplicationStatus::Interview, ApplicationStatus::Positive],
            '' => [],
            default => array_filter([ApplicationStatus::tryFrom($this->status)]),
        };

        return Auth::user()->jobApplications()
            ->with('company')
            ->when($statuses, fn ($q) => $q->withStatus(...$statuses))
            ->when($this->search, fn ($q) => $q->whereHas('company', fn ($c) => $c->where('name', 'like', '%'.$this->search.'%')->orWhere('city', 'like', '%'.$this->search.'%')))
            ->orderByRaw('sent_at is null')
            ->orderByDesc('sent_at')
            ->orderBy('priority')
            ->orderBy('distance_km')
            ->paginate(25);
    }

    #[Computed]
    public function selected(): ?JobApplication
    {
        return $this->selectedId
            ? Auth::user()->jobApplications()->with(['company', 'replies', 'sentEmails'])->find($this->selectedId)
            : null;
    }

    public function show(int $id): void
    {
        $this->selectedId = $id;
        $this->modal('details')->show();
    }

    public function setStatus(int $id, string $status): void
    {
        validator(['value' => $status], ['value' => ['required', Rule::in(array_map(fn ($s) => $s->value, ApplicationStatus::manualChoices()))]])->validate();

        $application = Auth::user()->jobApplications()->with('company')->findOrFail($id);
        $new = ApplicationStatus::from($status);
        $application->status = $new;
        $application->addNote('statut mis par toi : '.$new->label());
        $application->save();

        if ($new === ApplicationStatus::DoNotContact) {
            $application->company->update(['blocked' => true]); // "do not contact" applies to every student
        }
        if ($new === ApplicationStatus::InternshipSecured) {
            Auth::user()->profile->update(['internship_found_at' => now(), 'paused' => true]);
        }

        ActivityLog::record(Auth::user(), 'status_changed', $new->label(), $application);
        Flux::toast(text: $application->company->name.' : '.$new->label());
    }
}
