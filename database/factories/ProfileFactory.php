<?php

namespace Database\Factories;

use App\Enums\ValidationMode;
use App\Models\Profile;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'program_id' => fn () => Program::firstOrCreate(['name' => 'BTS SIO option SLAM'])->id,
            'school' => 'Lycée Mathias, Chalon-sur-Saône',
            'internship_start' => now()->addMonths(3)->toDateString(),
            'internship_end' => now()->addMonths(3)->addWeeks(6)->toDateString(),
            'address' => '1 place de l\'Hôtel de Ville, 71100 Chalon-sur-Saône',
            'city' => 'Chalon-sur-Saône',
            'latitude' => 46.7806,
            'longitude' => 4.8539,
            'radius_km' => 35,
            'has_driving_licence' => true,
            'phone' => '06 01 02 03 04',
            'github_url' => 'https://github.com/exemple',
            'daily_quota' => 20,
            'validation_mode' => ValidationMode::Manual,
            'paused' => false,
        ];
    }
}
