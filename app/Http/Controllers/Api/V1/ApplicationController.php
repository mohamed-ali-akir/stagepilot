<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateApplicationStatusRequest;
use App\Http\Resources\JobApplicationResource;
use App\Models\JobApplication;
use App\Services\ApplicationStatusChanger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * /api/v1/applications: the student's applications (never anybody else's).
 */
class ApplicationController extends Controller
{
    /** GET /api/v1/applications?status=sent,followed_up&search=chalon&per_page=20 */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $statuses = array_filter(explode(',', $validated['status'] ?? ''));
        validator(['status' => $statuses], ['status.*' => [Rule::enum(ApplicationStatus::class)]])->validate();

        $applications = $request->user()->jobApplications()
            ->with('company')
            ->withCount(['replies' => fn ($q) => $q->where('type', 'reply')])
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->when($validated['search'] ?? null, fn ($q, string $search) => $q->whereHas(
                'company', fn ($c) => $c->where('name', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%")
            ))
            ->latest('updated_at')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return JobApplicationResource::collection($applications);
    }

    /** GET /api/v1/applications/{id} */
    public function show(JobApplication $application): JobApplicationResource
    {
        Gate::authorize('view', $application);

        return new JobApplicationResource($application->load('company')->loadCount('replies'));
    }

    /** PATCH /api/v1/applications/{id}/status  {"status": "interview"} */
    public function updateStatus(UpdateApplicationStatusRequest $request, JobApplication $application, ApplicationStatusChanger $changer): JobApplicationResource
    {
        $changer->change($application->load('company', 'user.profile'), $request->status());

        return new JobApplicationResource($application->fresh('company'));
    }
}
