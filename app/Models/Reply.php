<?php

namespace App\Models;

use App\Enums\ReplyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }
}
