<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * French activity code (code NAF / APE), e.g. 62.01Z = computer programming.
 *
 * @property-read NafCodeProgram $pivot the priority (A, B, C), when loaded through Program::nafCodes()
 */
#[Table(key: 'code', keyType: 'string', incrementing: false)]
#[Fillable(['code', 'label'])]
class NafCode extends Model
{
    /** @return BelongsToMany<Program, $this, NafCodeProgram, 'pivot'> */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'naf_code_program', 'naf_code', 'program_id')
            ->using(NafCodeProgram::class)->withPivot('priority');
    }
}
