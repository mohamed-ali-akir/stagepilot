<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shared catalog: one row per SIREN, reused by every student (a website or email found once serves everyone).
 *
 * @property float|null $latitude
 * @property float|null $longitude
 * @property bool $website_searched
 * @property bool $email_searched
 * @property bool $blocked
 * @property CarbonImmutable|null $verified_at
 */
#[Table(key: 'siren', keyType: 'string', incrementing: false)]
#[Fillable([
    'siren', 'name', 'naf_code', 'activity', 'headcount', 'address', 'postal_code', 'city', 'latitude',
    'longitude', 'website', 'website_proof', 'website_searched', 'email', 'email_note', 'email_searched',
    'phone', 'blocked', 'verified_at',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /** Business rule 3: at most this many applications per company per month, all students together. */
    public const MONTHLY_APPLICATION_LIMIT = 2;

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'website_searched' => 'boolean',
            'email_searched' => 'boolean',
            'blocked' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /** @return HasMany<JobApplication, $this> */
    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'company_siren', 'siren');
    }

    public function applicationsSentThisMonth(): int
    {
        return $this->jobApplications()->where('sent_at', '>=', now()->subMonth())->count();
    }
}
