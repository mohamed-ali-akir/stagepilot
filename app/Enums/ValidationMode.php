<?php

namespace App\Enums;

enum ValidationMode: string
{
    /** Every hook waits for the student's approval. */
    case Manual = 'manual';

    /** Hooks that pass every check are ready the next day, unless the student cancels them. */
    case AutoWithVeto = 'auto_with_veto';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Je valide chaque accroche',
            self::AutoWithVeto => 'Automatique, avec une nuit pour annuler',
        };
    }
}
