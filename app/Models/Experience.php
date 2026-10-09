<?php

namespace App\Models;

use App\Enums\ExperienceRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An internship, job or project. The role (solo / team) keeps generated hooks honest.
 */
#[Fillable(['user_id', 'title', 'organization', 'started_at', 'ended_at', 'description', 'role', 'in_production', 'technologies'])]
class Experience extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
            'role' => ExperienceRole::class,
            'in_production' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** One line given to the AI to describe this experience, without exaggeration. */
    public function factsForPrompt(): string
    {
        $facts = [$this->title];
        if ($this->organization) {
            $facts[] = 'chez '.$this->organization;
        }
        $facts[] = '('.($this->role === ExperienceRole::Solo ? 'travail personnel' : 'travail en équipe, il a contribué / participé').')';
        $facts[] = ': '.$this->description;
        if ($this->technologies) {
            $facts[] = '. Technologies : '.$this->technologies;
        }
        $facts[] = $this->in_production ? '. Utilisé en production.' : '.';

        return implode(' ', $facts);
    }
}
