<?php

namespace App\Services\Hooks;

use Anthropic\Lib\Attributes\Constrained;
use Anthropic\Lib\Concerns\StructuredOutputModelTrait;
use Anthropic\Lib\Contracts\StructuredOutputModel;

/**
 * What Claude must answer (structured output): the hook, plus the proof taken from the company's site.
 */
class HookProposal implements StructuredOutputModel
{
    use StructuredOutputModelTrait;

    #[Constrained(description: 'false si l\'entreprise n\'a pas de lien avec le développement logiciel, le web ou le numérique, ou si le texte du site est trop pauvre pour savoir ce qu\'elle fait')]
    public bool $relevant;

    #[Constrained(description: 'Si relevant = false : la raison en quelques mots. Sinon une chaîne vide.')]
    public string $reason;

    #[Constrained(description: 'Une seule phrase en français (60 à 350 caractères) qui vouvoie l\'entreprise : ce qu\'elle fait d\'après son site + pourquoi ça intéresse l\'étudiant, appuyé sur son parcours réel. Chaîne vide si relevant = false.')]
    public string $hook;

    #[Constrained(description: 'Passage COPIÉ MOT POUR MOT du texte du site (40 à 200 caractères) qui prouve ce que dit l\'accroche sur l\'entreprise. Chaîne vide si relevant = false.')]
    public string $excerpt;

    #[Constrained(description: 'L\'URL indiquée dans l\'en-tête « === URL : ... === » de la section d\'où vient l\'extrait. Chaîne vide si relevant = false.')]
    public string $url;
}
