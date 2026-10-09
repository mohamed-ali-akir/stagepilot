<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\ReplyType;
use App\Models\ActivityLog;
use App\Models\JobApplication;
use App\Models\Reply;
use App\Models\SentEmail;
use App\Models\User;
use App\Services\Mail\IncomingMessage;
use App\Services\Mail\Mailbox;

/**
 * Reads the student's inbox (read-only) and recognises, for each application waiting for an answer:
 * - a real reply      -> status "reply_received" + a reply draft saved in Gmail
 * - an automatic one  -> only a note (out of office...), the follow-up still happens
 * - a delivery error  -> status "do_not_contact" (the address does not exist)
 * Statuses the student set by hand are never changed.
 */
class ReplyChecker
{
    public function __construct(private Mailbox $mailbox, private EmailComposer $composer) {}

    /**
     * @return array{replies: int, automatic: int, bounces: int}
     */
    public function check(User $user): array
    {
        $stats = ['replies' => 0, 'automatic' => 0, 'bounces' => 0];
        $waiting = $user->jobApplications()
            ->with('company')
            ->withStatus(...ApplicationStatus::awaitingReply())
            ->get();
        if ($waiting->isEmpty()) {
            return $stats;
        }

        $since = $waiting->min('sent_at') ?? now()->subMonth();
        $byEmail = $waiting->keyBy(fn ($a) => strtolower((string) $a->company->email));
        $byMessageId = SentEmail::whereIn('job_application_id', $waiting->pluck('id'))->pluck('job_application_id', 'message_id');
        $known = Reply::whereIn('job_application_id', $user->jobApplications()->select('id'))->pluck('message_id')->filter()->flip();

        foreach ($this->mailbox->messagesSince($user, $since) as $message) {
            if ($message->messageId !== '' && isset($known[$message->messageId])) {
                continue;
            }
            if (str_contains($message->from, strtolower($user->email))) {
                continue;
            }

            if ($message->isBounce()) {
                foreach (array_intersect($message->failedRecipients, $byEmail->keys()->all()) as $address) {
                    $this->bounce($user, $byEmail[$address], $message);
                    $stats['bounces']++;
                }

                continue;
            }

            $application = $this->match($message, $byMessageId, $byEmail);
            if (! $application || ! in_array($application->status, ApplicationStatus::awaitingReply(), true)) {
                continue;
            }
            if ($message->date->lt($application->sent_at->startOfDay())) {
                continue;
            }

            if ($message->automatic) {
                $this->record($application, $message, ReplyType::AutoReply);
                $application->addNote('réponse automatique : '.mb_substr($message->subject, 0, 80));
                $application->save();
                $stats['automatic']++;

                continue;
            }

            $reply = $this->record($application, $message, ReplyType::Reply);
            $application->status = ApplicationStatus::ReplyReceived;
            $application->addNote('réponse de '.$message->from.' : '.mb_substr($message->subject, 0, 80));
            $application->save();
            ActivityLog::record($user, 'reply_received', $message->from.' : '.$message->subject, $application);
            $this->saveDraft($user, $message, $reply);
            $stats['replies']++;
        }

        return $stats;
    }

    /** Linked by the Message-ID of our email (works even if they answer from another address), else by domain. */
    private function match(IncomingMessage $message, $byMessageId, $byEmail): ?JobApplication
    {
        foreach ($message->references as $reference) {
            if (isset($byMessageId[$reference])) {
                return JobApplication::with('company')->find($byMessageId[$reference]);
            }
        }

        $domain = substr(strrchr($message->from, '@') ?: '', 1);
        foreach ($byEmail as $email => $application) {
            $companyDomain = substr(strrchr($email, '@') ?: '', 1);
            $isProvider = in_array($companyDomain, ['gmail.com', 'hotmail.fr', 'hotmail.com', 'outlook.fr', 'outlook.com', 'orange.fr', 'wanadoo.fr', 'free.fr', 'yahoo.fr', 'laposte.net'], true);
            if ($message->from === $email || (! $isProvider && $domain !== '' && ($domain === $companyDomain || str_ends_with($domain, '.'.$companyDomain)))) {
                return $application;
            }
        }

        return null;
    }

    private function bounce(User $user, JobApplication $application, IncomingMessage $message): void
    {
        if (! in_array($application->status, ApplicationStatus::awaitingReply(), true)) {
            return;
        }
        $this->record($application, $message, ReplyType::Bounce);
        $application->status = ApplicationStatus::DoNotContact;
        $application->addNote('adresse invalide (mail revenu en erreur)');
        $application->save();
        ActivityLog::record($user, 'bounce', $application->company->email, $application);
    }

    private function record(JobApplication $application, IncomingMessage $message, ReplyType $type): Reply
    {
        return $application->replies()->create([
            'type' => $type,
            'sender' => mb_substr($message->from, 0, 250),
            'subject' => mb_substr($message->subject, 0, 250),
            'message_id' => $message->messageId !== '' ? $message->messageId : null,
            'received_at' => $message->date,
        ]);
    }

    private function saveDraft(User $user, IncomingMessage $message, Reply $reply): void
    {
        $subject = preg_match('/^re\s?:/i', $message->subject) ? $message->subject : 'Re: '.$message->subject;
        try {
            $this->mailbox->saveDraft($user, $message->replyTo ?: $message->from, $subject, $this->composer->replyDraft($user), $message->messageId);
            $reply->update(['draft_created' => true]);
        } catch (\Throwable $e) {
            ActivityLog::record($user, 'draft_failed', $e->getMessage(), $reply->jobApplication);
        }
    }
}
