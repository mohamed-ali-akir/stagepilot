<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Link table between a study program and an activity code: how well the code fits the program.
 *
 * @property string $priority A (best fit) to C (last resort)
 */
class NafCodeProgram extends Pivot {}
