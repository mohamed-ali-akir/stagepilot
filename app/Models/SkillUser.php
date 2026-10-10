<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Link table between a student and a skill: holds the student's level.
 *
 * @property string $level basic | good | very_good (see App\Enums\SkillLevel)
 */
class SkillUser extends Pivot {}
