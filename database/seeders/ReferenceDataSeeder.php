<?php

namespace Database\Seeders;

use App\Models\NafCode;
use App\Models\Program;
use Illuminate\Database\Seeder;

/**
 * Study programs and the NAF codes each one targets (A = best fit, C = last resort).
 * Taken from the Python prototype (config.py).
 */
class ReferenceDataSeeder extends Seeder
{
    public const NAF_CODES = [
        '62.01Z' => 'Programmation informatique',
        '62.02A' => 'Conseil en systèmes et logiciels informatiques',
        '62.03Z' => 'Gestion d\'installations informatiques',
        '62.09Z' => 'Autres activités informatiques',
        '63.11Z' => 'Traitement de données, hébergement',
        '58.21Z' => 'Édition de jeux électroniques',
        '58.29A' => 'Édition de logiciels système et de réseau',
        '58.29C' => 'Édition de logiciels applicatifs',
        '61.10Z' => 'Télécommunications filaires',
        '73.11Z' => 'Activités des agences de publicité (agences web)',
        '95.11Z' => 'Réparation d\'ordinateurs',
    ];

    public const PROGRAMS = [
        'BTS SIO option SLAM' => [
            '62.01Z' => 'A', '58.29C' => 'A', '62.02A' => 'A',
            '58.29A' => 'B', '62.09Z' => 'B', '63.11Z' => 'B', '73.11Z' => 'B', '58.21Z' => 'B',
            '62.03Z' => 'C', '95.11Z' => 'C',
        ],
        'BTS SIO option SISR' => [
            '62.03Z' => 'A', '62.02A' => 'A', '63.11Z' => 'A',
            '62.09Z' => 'B', '95.11Z' => 'B', '61.10Z' => 'B', '62.01Z' => 'B',
            '58.29A' => 'C',
        ],
        'BUT Informatique' => [
            '62.01Z' => 'A', '58.29C' => 'A', '62.02A' => 'A',
            '58.29A' => 'B', '62.09Z' => 'B', '63.11Z' => 'B', '58.21Z' => 'B',
            '73.11Z' => 'C', '62.03Z' => 'C',
        ],
    ];

    public function run(): void
    {
        foreach (self::NAF_CODES as $code => $label) {
            NafCode::updateOrCreate(['code' => $code], ['label' => $label]);
        }

        foreach (self::PROGRAMS as $name => $codes) {
            $program = Program::firstOrCreate(['name' => $name]);
            $program->nafCodes()->sync(
                collect($codes)->mapWithKeys(fn ($priority, $code) => [$code => ['priority' => $priority]])->all()
            );
        }
    }
}
