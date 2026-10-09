<?php

namespace App\Enums;

enum SentEmailType: string
{
    case Application = 'application';
    case FollowUp = 'follow_up';
}
