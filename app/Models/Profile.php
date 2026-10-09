<?php

namespace App\Models;

use App\Enums\ValidationMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Answers to the onboarding questionnaire: program, internship dates, location and sending preferences.
 */
#[Fillable([
    'user_id', 'program_id', 'school', 'internship_start', 'internship_end', 'address', 'city',
    'latitude', 'longitude', 'radius_km', 'has_driving_licence', 'phone', 'github_url', 'portfolio_url',
    'cv_path', 'daily_quota', 'validation_mode', 'paused', 'internship_found_at',
])]
class Profile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'internship_start' => 'date',
            'internship_end' => 'date',
            'latitude' => 'float',
            'longitude' => 'float',
            'has_driving_licence' => 'boolean',
            'paused' => 'boolean',
            'internship_found_at' => 'datetime',
            'validation_mode' => ValidationMode::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** Weeks between start and end of the internship, e.g. "6 semaines". */
    public function durationLabel(): string
    {
        $weeks = (int) round($this->internship_start->diffInDays($this->internship_end) / 7);

        return $weeks.' semaine'.($weeks > 1 ? 's' : '');
    }

    /** Applications stop being sent 15 days before the internship starts. */
    public function lastSendingDay(): \Carbon\CarbonInterface
    {
        return $this->internship_start->copy()->subDays(15);
    }
}
