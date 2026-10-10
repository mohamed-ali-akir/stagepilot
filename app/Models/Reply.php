<?php

namespace App\Models;

use App\Enums\ReplyType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ReplyType $type
 * @property CarbonImmutable|null $received_at
 * @property bool $draft_created
 */
#[Fillable(['job_application_id', 'type', 'sender', 'subject', 'message_id', 'received_at', 'draft_created'])]
class Reply extends Model
{
    protected function casts(): array
    {
        return [
            'type' => ReplyType::class,
            'received_at' => 'datetime',
            'draft_created' => 'boolean',
        ];
    }

    /** @return BelongsTo<JobApplication, $this> */
    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }
}
