<?php

namespace App\Enums;

enum ReplyType: string
{
    case Reply = 'reply';
    case AutoReply = 'auto_reply';
    case Bounce = 'bounce';

    public function label(): string
    {
        return match ($this) {
            self::Reply => 'Réponse',
            self::AutoReply => 'Réponse automatique',
            self::Bounce => 'Adresse invalide',
        };
    }
}
