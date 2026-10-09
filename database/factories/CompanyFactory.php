<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->lexify('Société ????');

        return [
            'siren' => (string) fake()->unique()->numberBetween(100000000, 999999999),
            'name' => $name,
            'naf_code' => '62.01Z',
            'headcount' => '10-19',
            'address' => '1 rue de la Paix 71100 CHALON-SUR-SAONE',
            'postal_code' => '71100',
            'city' => 'Chalon-sur-Saône',
            'latitude' => 46.78,
            'longitude' => 4.85,
            'website' => 'https://www.'.\Illuminate\Support\Str::slug($name).'.fr/',
            'website_searched' => true,
            'email' => 'contact@'.\Illuminate\Support\Str::slug($name).'.fr',
            'email_searched' => true,
            'phone' => '03 85 00 00 00',
            'blocked' => false,
        ];
    }
}
