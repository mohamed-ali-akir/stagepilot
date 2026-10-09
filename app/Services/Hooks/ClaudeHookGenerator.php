<?php

namespace App\Services\Hooks;

use Anthropic\Client;
use App\Enums\SkillLevel;
use App\Models\Company;
use App\Models\User;

/**
 * Asks Claude (Anthropic API) for a hook, as structured output (see HookProposal).
 * The answer is then checked by HookVerifier: Claude is never trusted blindly.
 */
class ClaudeHookGenerator implements HookGenerator
{
    public function __construct(private ?string $apiKey, private string $model) {}

    public function propose(User $user, Company $company, string $siteText): HookProposal|string
    {
        if (blank($this->apiKey)) {
            return 'clé API Anthropic absente (ANTHROPIC_API_KEY dans .env)';
        }
        if (trim($siteText) === '') {
            return 'texte du site vide';
        }

        try {
            $client = new Client(apiKey: $this->apiKey);
            $message = $client->messages->create(
                model: $this->model,
                maxTokens: 16000,
                system: $this->systemPrompt($user),
                messages: [['role' => 'user', 'content' => $this->userPrompt($company, $siteText)]],
                outputConfig: ['format' => HookProposal::class, 'effort' => 'low'],
            );
        } catch (\Throwable $e) {
            return 'erreur API Claude : '.class_basename($e).' '.mb_substr($e->getMessage(), 0, 150);
        }

        if ($message->stopReason === 'refusal') {
            return 'Claude a refusé de répondre';
        }

        $proposal = $message->parsedOutput();

        return $proposal instanceof HookProposal ? $proposal : 'réponse de Claude illisible';
    }

    private function systemPrompt(User $user): string
    {
        $profile = $user->profile()->with('program')->first();
        $experiences = $user->experiences->map(fn ($e) => '- '.$e->factsForPrompt())->implode("\n");
        $skills = $user->skills->map(fn ($s) => $s->name.' ('.SkillLevel::from($s->pivot->level)->label().')')->implode(', ');

        return <<<PROMPT
        Tu écris l'accroche d'une candidature spontanée de stage : UNE phrase qui s'insère au milieu du mail.

        L'étudiant : {$user->name}, {$profile->program->name}, {$profile->school}. Stage de {$profile->durationLabel()}.
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
        - L'extrait doit être copié mot pour mot depuis le texte fourni : il sera recherché automatiquement sur la page.
        - Si l'entreprise n'a pas de lien avec le développement logiciel, le web ou le numérique, ou si le texte est trop pauvre : relevant = false.
        PROMPT;
    }

    private function userPrompt(Company $company, string $siteText): string
    {
        return <<<PROMPT
        Entreprise : {$company->name} ({$company->city}). Activité déclarée : {$company->naf_code}.
        Texte de son site :

        {$siteText}
        PROMPT;
    }
}
