<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every automatic action, so the student can always see what the app did and why.
 */
#[Fillable(['user_id', 'job_application_id', 'action', 'details'])]
class ActivityLog extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    /** Action shown to the student, in French. */
    public function label(): string
    {
        return match ($this->action) {
            'company_search' => 'Recherche d\'entreprises',
            'prepare', 'prepare_requested' => 'Préparation des candidatures',
            'hook_written' => 'Accroche écrite et vérifiée',
            'hook_rejected' => 'Accroche refusée par la vérification',
            'hook_approved' => 'Accroche validée',
            'application_sent' => 'Candidature envoyée',
            'follow_up_sent' => 'Relance envoyée',
            'send_failed' => 'Envoi refusé par Gmail',
            'sending_skipped' => 'Pas d\'envoi',
            'sending_enabled' => 'Envois activés',
            'sending_paused' => 'Envois en pause',
            'reply_received' => 'Réponse reçue',
            'bounce' => 'Adresse invalide',
            'draft_failed' => 'Brouillon de réponse impossible',
            'status_changed' => 'Statut modifié',
            'internship_found' => 'Stage trouvé',
            default => $this->action,
        };
    }

    public static function record(User $user, string $action, ?string $details = null, ?JobApplication $application = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'job_application_id' => $application?->id,
            'action' => $action,
            'details' => $details,
        ]);
    }
}
