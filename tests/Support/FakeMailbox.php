<?php

namespace Tests\Support;

use App\Models\User;
use App\Services\Mail\IncomingMessage;
use App\Services\Mail\Mailbox;
use Carbon\CarbonInterface;

/**
 * Gmail stand-in for tests: records what would be sent, returns prepared incoming messages.
 */
class FakeMailbox implements Mailbox
{
    /** @var list<array{to: string, subject: string, body: string, attachment: ?string, messageId: string}> */
    public array $sent = [];

    /** @var list<array{to: string, subject: string, body: string, inReplyTo: string}> */
    public array $drafts = [];

    /** @var list<IncomingMessage> */
    public array $inbox = [];

    /** Addresses Gmail refuses. */
    public array $refused = [];

    public function send(User $user, string $to, string $subject, string $body, ?string $attachmentPath, string $messageId): void
    {
        if (in_array($to, $this->refused, true)) {
            throw new \RuntimeException("550 adresse inconnue : $to");
        }
        $this->sent[] = ['to' => $to, 'subject' => $subject, 'body' => $body, 'attachment' => $attachmentPath, 'messageId' => $messageId];
    }

    public function messagesSince(User $user, CarbonInterface $since): array
    {
        return $this->inbox;
    }

    public function saveDraft(User $user, string $to, string $subject, string $body, string $inReplyTo): void
    {
        $this->drafts[] = compact('to', 'subject', 'body', 'inReplyTo');
    }

    public function canConnect(string $email, string $appPassword): bool
    {
        return $appPassword === 'abcdefghijklmnop';
    }

    /** @return list<string> recipients of real (non-summary) emails */
    public function recipients(): array
    {
        return array_values(array_map(
            fn ($m) => $m['to'],
            array_filter($this->sent, fn ($m) => ! str_starts_with($m['subject'], '[StagePilot]')),
        ));
    }
}
