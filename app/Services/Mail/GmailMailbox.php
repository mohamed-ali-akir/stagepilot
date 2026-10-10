<?php

namespace App\Services\Mail;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/**
 * Gmail with the student's app password: SMTP to send, IMAP (read-only) to read replies and save drafts.
 */
class GmailMailbox implements Mailbox
{
    public function send(User $user, string $to, string $subject, string $body, ?string $attachmentPath, string $messageId): void
    {
        $this->refuseDemo($user);
        $email = (new Email)
            ->from(new Address($user->email, $user->name))
            ->to($to)
            ->subject($subject)
            ->text($body);
        $email->getHeaders()->addIdHeader('Message-ID', trim($messageId, '<>'));
        if ($attachmentPath && is_file($attachmentPath)) {
            $email->attachFromPath($attachmentPath, basename($attachmentPath), 'application/pdf');
        }

        $this->mailer($user->email, (string) $user->gmail_app_password)->send($email);
    }

    public function messagesSince(User $user, CarbonInterface $since): array
    {
        $this->refuseDemo($user);
        $client = $this->imap($user->email, (string) $user->gmail_app_password);
        $messages = [];
        try {
            $inbox = $client->getFolderByPath('INBOX');
            $found = $inbox->query()->whereSince($since->copy()->subDay())->leaveUnread()->setFetchBody(true)->get();
            foreach ($found as $message) {
                $messages[] = $this->toIncoming($message);
            }
        } finally {
            $client->disconnect();
        }

        return $messages;
    }

    public function saveDraft(User $user, string $to, string $subject, string $body, string $inReplyTo): void
    {
        $this->refuseDemo($user);
        $draft = (new Email)
            ->from(new Address($user->email, $user->name))
            ->to($to)
            ->subject($subject)
            ->text($body);
        $draft->getHeaders()->addIdHeader('In-Reply-To', trim($inReplyTo, '<>'));
        $draft->getHeaders()->addIdHeader('References', trim($inReplyTo, '<>'));

        $client = $this->imap($user->email, (string) $user->gmail_app_password);
        try {
            $folder = collect($client->getFolders(false))->first(fn ($f): bool => (bool) preg_match('/draft|brouillon/i', $f->path));
            if ($folder === null) {
                throw new \RuntimeException('dossier Brouillons introuvable');
            }
            $folder->appendMessage($draft->toString(), ['\\Draft'], Carbon::now());
        } finally {
            $client->disconnect();
        }
    }

    public function canConnect(string $email, string $appPassword): bool
    {
        try {
            $transport = new EsmtpTransport('smtp.gmail.com', 465, true);
            $transport->setUsername($email);
            $transport->setPassword($appPassword);
            $transport->start();
            $transport->stop();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Last lock: even if everything else failed, the demo account never reaches Gmail. */
    private function refuseDemo(User $user): void
    {
        if ($user->is_demo) {
            throw new \LogicException('Compte de démonstration : Gmail est désactivé.');
        }
    }

    private function mailer(string $email, string $appPassword): Mailer
    {
        $transport = new EsmtpTransport('smtp.gmail.com', 465, true);
        $transport->setUsername($email);
        $transport->setPassword(str_replace(' ', '', $appPassword));

        return new Mailer($transport);
    }

    private function imap(string $email, string $appPassword): Client
    {
        $client = (new ClientManager)->make([
            'host' => 'imap.gmail.com',
            'port' => 993,
            'encryption' => 'ssl',
            'validate_cert' => true,
            'username' => $email,
            'password' => str_replace(' ', '', $appPassword),
            'protocol' => 'imap',
        ]);

        return $client->connect();
    }

    private function toIncoming(Message $message): IncomingMessage
    {
        $headers = iconv_mime_decode_headers($message->getHeader()->raw, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: [];
        $first = fn ($value) => is_array($value) ? (string) reset($value) : (string) $value;

        $from = $first($headers['From'] ?? '');
        preg_match('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $from, $fromEmail);
        preg_match('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $first($headers['Reply-To'] ?? ''), $replyTo);
        preg_match_all('/<[^>]+>/', $first($headers['In-Reply-To'] ?? '').' '.$first($headers['References'] ?? ''), $refs);

        $failed = [];
        if (! empty($headers['X-Failed-Recipients'])) {
            preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $first($headers['X-Failed-Recipients']), $f);
            $failed = array_map('strtolower', $f[0]);
        } elseif (preg_match('/mailer-daemon|postmaster/i', $from)) {
            preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', mb_substr($message->getTextBody(), 0, 5000), $f);
            $failed = array_values(array_unique(array_map('strtolower', $f[0])));
        }

        $subject = $first($headers['Subject'] ?? '');
        try {
            $date = Carbon::parse($first($headers['Date'] ?? 'now'));
        } catch (\Throwable) {
            $date = Carbon::now();
        }

        $incoming = new IncomingMessage(
            from: strtolower($fromEmail[0] ?? $from),
            subject: $subject,
            date: $date,
            messageId: $first($headers['Message-ID'] ?? ($headers['Message-Id'] ?? '')),
            references: $refs[0],
            automatic: IncomingMessage::looksAutomatic($subject, $headers),
            failedRecipients: [],
            replyTo: strtolower($replyTo[0] ?? ''),
        );
        $incoming->failedRecipients = $incoming->isBounce() || ! empty($headers['X-Failed-Recipients']) ? $failed : [];

        return $incoming;
    }
}
