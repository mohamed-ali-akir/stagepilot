<?php

namespace App\Models;

use App\Enums\SentEmailType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log of every email really sent. The unique Message-ID prevents duplicates and links replies back.
 *
 * @property SentEmailType $type
 * @property CarbonImmutable|null $sent_at
 */
#[Fillable(['job_application_id', 'type', 'recipient', 'message_id', 'sent_at'])]
class SentEmail extends Model
{
    protected function casts(): array
    {
        return [
            'type' => SentEmailType::class,
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<JobApplication, $this> */
    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }
}
