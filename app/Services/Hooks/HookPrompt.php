<?php

namespace App\Services\Hooks;

use App\Enums\SkillLevel;
use App\Models\Company;
use App\Models\User;

/**
 * The instructions given to any AI (Claude, Mistral, Ollama): same rules for all of them.
 */
class HookPrompt
{
    public static function system(User $user): string
    {
        $profile = $user->profile()->with('program')->first();
        $experiences = $user->experiences->map(fn ($e) => '- '.$e->factsForPrompt())->implode("\n");
        $skills = $user->skills->map(fn ($s) => $s->name.' ('.SkillLevel::from($s->pivot->level)->label().')')->implode(', ');

        return <<<PROMPT
        Tu écris l'accroche d'une candidature spontanée de stage : UNE phrase qui s'insère au milieu du mail.

        L'étudiant : {$profile->program->name}, {$profile->school}. Stage de {$profile->durationLabel()}.
        Ses expériences (faits vérifiés, à ne jamais exagérer) :
        {$experiences}
        Ses compétences : {$skills}

        Règles impératives :
        - Une seule phrase en français, 60 à 350 caractères, qui commence par une majuscule et finit par un point.
        - Structure : ce que fait l'entreprise (d'après le texte de son site uniquement) + pourquoi ça intéresse l'étudiant, en s'appuyant sur UNE de ses expériences réelles.
        - Vouvoie l'entreprise (« Vos logiciels… », « Votre agence… »).
        - N'invente rien sur l'entreprise. N'invente rien sur l'étudiant.
        - Pour un travail en équipe, écris « j'ai contribué » ou « j'ai participé », jamais « j'ai développé / créé / conçu » ni « seul ».
        - Pas de slogan recopié, pas de flatterie (leader, incontournable, excellence…), pas de point d'exclamation, pas de tiret cadratin, pas de lien, pas de « expert » ni « je maîtrise ».
        - L'extrait doit être copié mot pour mot depuis le TEXTE DU SITE (jamais depuis le profil de l'étudiant ni depuis l'accroche) : il sera recherché automatiquement sur la page. Choisis-le d'abord, puis écris l'accroche en reprenant un mot de cet extrait.
        - Si l'entreprise n'a pas de lien avec le développement logiciel, le web ou le numérique, ou si le texte est trop pauvre : relevant = false.
        PROMPT;
    }

    public static function user(Company $company, string $siteText): string
    {
        return <<<PROMPT
        Entreprise : {$company->name} ({$company->city}). Activité déclarée : {$company->naf_code}.
        Texte de son site :

        {$siteText}
        PROMPT;
    }

    /** JSON schema of the expected answer (same fields as HookProposal), for Mistral and Ollama. */
    public static function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'relevant' => ['type' => 'boolean', 'description' => 'false si l\'entreprise n\'a pas de lien avec le logiciel, le web ou le numérique, ou si le texte est trop pauvre'],
                'reason' => ['type' => 'string', 'description' => 'si relevant = false : la raison en quelques mots, sinon chaîne vide'],
                'url' => ['type' => 'string', 'description' => 'URL de l\'en-tête « === URL : ... === » de la section d\'où vient l\'extrait'],
                'excerpt' => ['type' => 'string', 'description' => 'passage copié mot pour mot du texte du site (40 à 200 caractères)'],
                'hook' => ['type' => 'string', 'description' => 'une seule phrase en français, 60 à 350 caractères, qui vouvoie l\'entreprise'],
            ],
            'required' => ['relevant', 'reason', 'url', 'excerpt', 'hook'],
            'additionalProperties' => false,
        ];
    }

    /** Turns the JSON text answered by an AI into a HookProposal (or an error message). */
    public static function parse(string $json): HookProposal|string
    {
        $data = json_decode(trim($json), true);
        if (! is_array($data) || ! array_key_exists('relevant', $data)) {
            return 'réponse de l\'IA illisible';
        }

        $proposal = new HookProposal;
        $proposal->relevant = (bool) $data['relevant'];
        $proposal->reason = (string) ($data['reason'] ?? '');
        $proposal->hook = trim((string) ($data['hook'] ?? ''));
        $proposal->excerpt = trim((string) ($data['excerpt'] ?? ''));
        $proposal->url = trim((string) ($data['url'] ?? ''));

        return $proposal;
    }
}
