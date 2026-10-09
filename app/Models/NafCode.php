<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * French activity code (code NAF / APE), e.g. 62.01Z = computer programming.
 */
#[Table(key: 'code', keyType: 'string', incrementing: false)]
#[Fillable(['code', 'label'])]
class NafCode extends Model
{
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'naf_code_program', 'naf_code', 'program_id')
            ->withPivot('priority');
    }
}
