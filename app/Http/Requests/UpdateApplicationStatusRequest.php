<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use App\Models\JobApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH /api/v1/applications/{application}/status
 * Only the owner (policy) may change it, and only to a status the student sets by hand.
 */
class UpdateApplicationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $application instanceof JobApplication && $this->user()?->can('update', $application) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_map(fn (ApplicationStatus $s) => $s->value, ApplicationStatus::manualChoices()))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.in' => 'Ce statut est posé par l\'application, pas à la main (choix possibles : positive, negative, interview, internship_secured, do_not_contact).',
        ];
    }

    public function status(): ApplicationStatus
    {
        return ApplicationStatus::from($this->validated('status'));
    }
}
