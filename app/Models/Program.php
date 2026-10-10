<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A study program (BTS SIO SLAM, BTS SIO SISR...). It decides which kinds of companies are targeted.
 */
#[Fillable(['name'])]
class Program extends Model
{
    /**
     * NAF codes targeted by this program, with a priority A (best fit) to C.
     *
     * @return BelongsToMany<NafCode, $this, NafCodeProgram, 'pivot'>
     */
    public function nafCodes(): BelongsToMany
    {
        return $this->belongsToMany(NafCode::class, 'naf_code_program', 'program_id', 'naf_code')
            ->using(NafCodeProgram::class)->withPivot('priority');
    }

    /** @return HasMany<Profile, $this> */
    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class);
    }
}
