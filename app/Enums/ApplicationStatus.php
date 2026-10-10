<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case PendingReview = 'pending_review';
    case Ready = 'ready';
    case Sent = 'sent';
    case FollowedUp = 'followed_up';
    case ReplyReceived = 'reply_received';
    case Positive = 'positive';
    case Negative = 'negative';
    case Interview = 'interview';
    case InternshipSecured = 'internship_secured';
    case Ignored = 'ignored';
    case DoNotContact = 'do_not_contact';

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => 'À valider',
            self::Ready => 'Prête',
            self::Sent => 'Envoyée',
            self::FollowedUp => 'Relancée',
            self::ReplyReceived => 'Réponse reçue',
            self::Positive => 'Réponse positive',
            self::Negative => 'Réponse négative',
            self::Interview => 'Entretien',
            self::InternshipSecured => 'Stage obtenu',
            self::Ignored => 'Ignorée',
            self::DoNotContact => 'Ne pas contacter',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingReview => 'amber',
            self::Ready => 'sky',
            self::Sent, self::FollowedUp => 'blue',
            self::ReplyReceived => 'violet',
            self::Positive, self::Interview => 'lime',
            self::InternshipSecured => 'green',
            self::Negative => 'red',
            self::Ignored, self::DoNotContact => 'zinc',
        };
    }

    /**
     * Statuses the student can set by hand after reading a reply.
     *
     * @return list<self>
     */
    public static function manualChoices(): array
    {
        return [self::Positive, self::Negative, self::Interview, self::InternshipSecured, self::DoNotContact];
    }

    /**
     * Statuses still waiting for an answer from the company.
     *
     * @return list<self>
     */
    public static function awaitingReply(): array
    {
        return [self::Sent, self::FollowedUp];
    }
}
