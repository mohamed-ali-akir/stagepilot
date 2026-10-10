<?php

namespace App\Services;

use App\Models\JobApplication;
use App\Models\Profile;
use App\Models\User;

/**
 * Writes the application and follow-up emails (same texts as the prototype's templates).
 */
class EmailComposer
{
    /**
     * @return array{subject: string, body: string}
     */
    public function application(User $user, JobApplication $application): array
    {
        $profile = $user->profile;
        $dates = 'du '.$profile->internship_start->translatedFormat('j F').' au '.$profile->internship_end->translatedFormat('j F Y');
        $links = $this->links($profile);

        $body = <<<TXT
        Bonjour,

        Je suis étudiant en {$profile->program->name} au {$profile->school}, et je recherche un stage de {$profile->durationLabel()} {$dates}.

        {$application->hook}

        Je serais heureux de mettre mes compétences au service de vos projets, tout en apprenant aux côtés de vos équipes.{$links}

        Vous trouverez mon CV en pièce jointe. Je reste disponible pour un échange par téléphone ou pour passer vous rencontrer.

        Bien cordialement,

        {$user->name}
        {$profile->phone}
        {$user->email}
        TXT;

        return [
            'subject' => "Candidature stage {$profile->program->name} – {$profile->durationLabel()} – {$user->name}",
            'body' => $this->clean($body),
        ];
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function followUp(User $user, JobApplication $application): array
    {
        $profile = $user->profile;
        $sentOn = $application->sent_at?->format('d/m/Y');

        $body = <<<TXT
        Bonjour,

        Je me permets de revenir vers vous au sujet de ma demande de stage de {$profile->durationLabel()}, envoyée le {$sentOn}.

        Je suis toujours très intéressé par {$application->company->name} et je serais ravi d'en discuter, même brièvement. Si ce n'est pas possible cette année, n'hésitez pas à me le dire : je ne vous relancerai pas.

        Je joins à nouveau mon CV.

        Bien cordialement,

        {$user->name}
        {$profile->phone}
        TXT;

        return [
            'subject' => "Re: Candidature stage {$profile->program->name} – {$user->name}",
            'body' => $this->clean($body),
        ];
    }

    /** Reply draft saved in Gmail when a company answers: the student completes it. */
    public function replyDraft(User $user): string
    {
        return $this->clean(<<<TXT
        Bonjour,

        Merci beaucoup pour votre réponse.

        [À COMPLÉTER AVANT D'ENVOYER]

        Je reste disponible par téléphone au {$user->profile->phone} ou pour passer vous rencontrer, selon ce qui vous arrange.

        Bien cordialement,

        {$user->name}
        {$user->profile->phone}
        TXT);
    }

    private function links(Profile $profile): string
    {
        $links = array_filter([
            $profile->portfolio_url ? '- Portfolio : '.$profile->portfolio_url : null,
            $profile->github_url ? '- GitHub : '.$profile->github_url : null,
        ]);

        return $links ? "\n\nVous pouvez voir mes projets ici :\n".implode("\n", $links) : '';
    }

    private function clean(string $text): string
    {
        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
