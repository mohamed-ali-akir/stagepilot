<?php

namespace App\Enums;

enum ExperienceRole: string
{
    case Solo = 'solo';
    case Team = 'team';

    public function label(): string
    {
        return match ($this) {
            self::Solo => 'Seul',
            self::Team => 'En équipe',
        };
    }
}
