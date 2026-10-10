<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\JobApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One student applying to one company (unique pair). Holds the hook and the proof that it is true.
 *
 * @property ApplicationStatus $status
 * @property float|null $distance_km
 * @property bool $hook_generated
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $followed_up_at
 */
#[Fillable([
    'user_id', 'company_siren', 'status', 'priority', 'distance_km', 'hook', 'proof_excerpt', 'proof_url',
    'hook_generated', 'hook_error', 'approved_at', 'sent_at', 'followed_up_at', 'notes',
])]
class JobApplication extends Model
{
    /** @use HasFactory<JobApplicationFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'distance_km' => 'float',
            'hook_generated' => 'boolean',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'followed_up_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_siren', 'siren');
    }

    /** @return HasMany<SentEmail, $this> */
    public function sentEmails(): HasMany
    {
        return $this->hasMany(SentEmail::class);
    }

    /** @return HasMany<Reply, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(Reply::class);
    }

    /**
     * @param  Builder<JobApplication>  $query
     * @return Builder<JobApplication>
     */
    public function scopeWithStatus(Builder $query, ApplicationStatus ...$statuses): Builder
    {
        return $query->whereIn('status', array_map(fn ($s) => $s->value, $statuses));
    }

    public function addNote(string $note): void
    {
        $line = '['.now()->format('d/m').'] '.$note;
        if (! str_contains((string) $this->notes, $note)) {
            $this->notes = trim(($this->notes ? $this->notes."\n" : '').$line);
        }
    }
}
