<?php

namespace App\Policies;

use App\Models\JobApplication;
use App\Models\User;

/**
 * Who may do what with an application: only its owner, whatever the screen or the API used.
 * Laravel finds this class by its name (JobApplication -> JobApplicationPolicy).
 */
class JobApplicationPolicy
{
    public function view(User $user, JobApplication $application): bool
    {
        return $application->user_id === $user->id;
    }

    public function update(User $user, JobApplication $application): bool
    {
        return $application->user_id === $user->id;
    }
}
