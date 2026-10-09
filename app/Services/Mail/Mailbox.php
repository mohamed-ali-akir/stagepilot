<?php

namespace App\Services\Mail;

use App\Models\User;
use Carbon\CarbonInterface;

/**
 * The student's Gmail: send emails (SMTP), read replies (IMAP, read-only), save drafts.
 * Implemented by GmailMailbox; replaced by a fake in tests.
 */
interface Mailbox
{
    /**
     * @param  string  $messageId  "<...@gmail.com>", stored so replies can be linked back
     */
    public function send(User $user, string $to, string $subject, string $body, ?string $attachmentPath, string $messageId): void;

    /**
     * @return list<IncomingMessage>
     */
    public function messagesSince(User $user, CarbonInterface $since): array;

    /** Saves a reply draft in Gmail > Drafts. It is NEVER sent. */
    public function saveDraft(User $user, string $to, string $subject, string $body, string $inReplyTo): void;

    /** Tries to log in (used to check the app password during onboarding). */
    public function canConnect(string $email, string $appPassword): bool;
}
