<?php

namespace App\Services\Mail;

use Carbon\CarbonInterface;

/**
 * An email found in the student's mailbox (only what we need to recognise replies).
 */
class IncomingMessage
{
    /**
     * @param  list<string>  $references  Message-IDs this email answers (In-Reply-To + References)
     * @param  list<string>  $failedRecipients  for delivery failures (bounces)
     */
    public function __construct(
        public string $from,
        public string $subject,
        public CarbonInterface $date,
        public string $messageId,
        public array $references = [],
        public bool $automatic = false,
        public array $failedRecipients = [],
        public string $replyTo = '',
    ) {}

    public function isBounce(): bool
    {
        if ($this->failedRecipients !== []) {
            return true;
        }
        if (! preg_match('/mailer-daemon|postmaster/i', $this->from)) {
            return false;
        }
        if (preg_match('/delay|retard|diff[ée]r|temporar/i', $this->subject)) {
            return false;
        }

        return (bool) preg_match('/failure|undeliver|non remis|échec|echec|address not found|returned mail|delivery status|introuvable/iu', $this->subject);
    }

    /**
     * Out-of-office, acknowledgements, no-reply robots...
     *
     * @param  array<string, string|list<string>>  $headers
     */
    public static function looksAutomatic(string $subject, array $headers): bool
    {
        // a header sent twice comes as a list: keep the first value
        $headers = array_map(fn ($value) => is_array($value) ? (string) reset($value) : $value, $headers);
        $auto = strtolower(trim((string) ($headers['Auto-Submitted'] ?? '')));
        if ($auto !== '' && $auto !== 'no') {
            return true;
        }
        foreach (['X-Autoreply', 'X-Autorespond', 'X-Autoresponse'] as $header) {
            if (! empty($headers[$header])) {
                return true;
            }
        }
        if (in_array(strtolower(trim((string) ($headers['Precedence'] ?? ''))), ['auto_reply', 'bulk', 'junk', 'list'], true)) {
            return true;
        }

        return (bool) preg_match('/r[ée]ponse automatique|automatic reply|auto.?reply|out of office|absen|accus[ée] de r[ée]ception|message automatique|cong[ée]s|vacances|ne pas r[ée]pondre|do not reply|no.?reply/iu', $subject);
    }
}
