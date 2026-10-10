<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\ActivityLog;
use App\Models\JobApplication;

/**
 * A status chosen by the student after reading a company's reply (interview, refusal...).
 * Shared by the "Candidatures" screen and the REST API, so the business rules live in one place.
 */
class ApplicationStatusChanger
{
    public function change(JobApplication $application, ApplicationStatus $status): JobApplication
    {
        if (! in_array($status, ApplicationStatus::manualChoices(), true)) {
            throw new \InvalidArgumentException("Le statut « {$status->label()} » est posé par l'application, pas à la main.");
        }

        $application->status = $status;
        $application->addNote('statut mis par toi : '.$status->label());
        $application->save();

        if ($status === ApplicationStatus::DoNotContact) {
            $application->company->update(['blocked' => true]); // "do not contact" applies to every student
        }
        if ($status === ApplicationStatus::InternshipSecured) {
            $application->user->profile?->update(['internship_found_at' => now(), 'paused' => true]);
        }

        ActivityLog::record($application->user, 'status_changed', $status->label(), $application);

        return $application;
    }
}
