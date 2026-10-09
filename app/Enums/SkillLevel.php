<?php

namespace App\Enums;

enum SkillLevel: string
{
    case Basic = 'basic';
    case Good = 'good';
    case VeryGood = 'very_good';

    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Notions',
            self::Good => 'Bon',
            self::VeryGood => 'Très bon',
        };
    }
}
