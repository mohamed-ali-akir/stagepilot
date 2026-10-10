<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read SkillUser $pivot the student's level, when loaded through User::skills()
 */
#[Fillable(['name', 'category'])]
class Skill extends Model
{
    /** @return BelongsToMany<User, $this, SkillUser, 'pivot'> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->using(SkillUser::class)->withPivot('level');
    }
}
