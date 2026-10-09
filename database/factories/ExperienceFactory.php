<?php

namespace Database\Factories;

use App\Enums\ExperienceRole;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Experience>
 */
class ExperienceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => 'Stage développeur web',
            'organization' => 'Nex Informatique',
            'description' => 'Migration module par module d\'une application métier PHP vers Laravel (Eloquent, Blade, droits).',
            'role' => ExperienceRole::Team,
            'in_production' => true,
            'technologies' => 'PHP, Laravel, MySQL',
        ];
    }
}
