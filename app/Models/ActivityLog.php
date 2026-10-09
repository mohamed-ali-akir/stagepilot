<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every automatic action, so the student can always see what the app did and why.
 */
#[Fillable(['user_id', 'job_application_id', 'action', 'details'])]
class ActivityLog extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public static function record(User $user, string $action, ?string $details = null, ?JobApplication $application = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'job_application_id' => $application?->id,
            'action' => $action,
            'details' => $details,
        ]);
    }
}
