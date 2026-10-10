<?php

namespace App\Livewire;

use App\Enums\ApplicationStatus;
use App\Models\JobApplication;
use App\Services\ApplicationStatusChanger;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    /** @return LengthAwarePaginator<int, JobApplication> */
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

    public function setStatus(int $id, string $status, ApplicationStatusChanger $changer): void
    {
        validator(['value' => $status], ['value' => ['required', Rule::in(array_map(fn ($s) => $s->value, ApplicationStatus::manualChoices()))]])->validate();

        $application = Auth::user()->jobApplications()->with('company')->findOrFail($id);
        $changer->change($application, ApplicationStatus::from($status));

        Flux::toast(text: $application->company->name.' : '.$application->status->label());
    }
}
