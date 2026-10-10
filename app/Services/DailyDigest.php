<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\User;
use App\Services\Mail\Mailbox;
use Illuminate\Support\Str;

/**
 * The morning summary emailed to the student (to read on the phone).
 */
class DailyDigest
{
    public function __construct(private Mailbox $mailbox) {}

    /** @param  array<string, int>  $replies */
    public function build(User $user, array $replies, int $queued, ?string $blockedReason): string
    {
        $count = fn (ApplicationStatus ...$s) => $user->jobApplications()->withStatus(...$s)->count();
        $lines = ["Bonjour {$user->name}, voici le point du ".now()->format('d/m/Y').' sur ta recherche de stage.', ''];

        if ($replies['replies'] > 0) {
            $lines[] = "{$replies['replies']} réponse(s) reçue(s) : un brouillon de réponse t'attend dans Gmail > Brouillons.";
        }
        if ($replies['bounces'] > 0) {
            $lines[] = "{$replies['bounces']} adresse(s) invalide(s), passée(s) en « ne pas contacter ».";
        }
        if ($replies['automatic'] > 0) {
            $lines[] = "{$replies['automatic']} réponse(s) automatique(s) (absence…).";
        }
        $lines[] = $blockedReason ? "Pas d'envoi aujourd'hui : $blockedReason." : "$queued mail(s) programmé(s) aujourd'hui.";
        $lines[] = '';
        $lines[] = 'À valider : '.$count(ApplicationStatus::PendingReview);
        $lines[] = 'Prêtes : '.$count(ApplicationStatus::Ready);
        $lines[] = 'En attente de réponse : '.$count(...ApplicationStatus::awaitingReply());
        $lines[] = 'Entretiens : '.$count(ApplicationStatus::Interview);
        $lines[] = '';
        $lines[] = 'Tableau de bord : '.route('dashboard');

        return implode("\n", $lines);
    }

    /** @param  array<string, int>  $replies */
    public function send(User $user, array $replies, int $queued, ?string $blockedReason): void
    {
        if (! $user->canSendEmails()) {
            return;
        }
        $subject = '[StagePilot] Résumé du '.now()->format('d/m').($replies['replies'] > 0 ? " : {$replies['replies']} réponse(s) à lire" : '');
        $messageId = '<digest-'.Str::uuid().'@'.Str::after($user->email, '@').'>';
        $this->mailbox->send($user, $user->email, $subject, $this->build($user, $replies, $queued, $blockedReason), null, $messageId);
    }
}
