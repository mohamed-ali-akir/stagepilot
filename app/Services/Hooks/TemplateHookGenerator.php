<?php

namespace App\Services\Hooks;

use App\Enums\ExperienceRole;
use App\Models\Company;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Free option, no AI at all: builds the hook from a sentence really found on the company's site
 * and from the student's own experience. Honest by construction, a bit more generic than an AI.
 *
 * 1. find the sentence of the site that best describes a digital activity (it becomes the proof),
 * 2. deduce the field of activity from that sentence,
 * 3. pick the student's experience that shares the most words with the site.
 */
class TemplateHookGenerator implements HookGenerator
{
    /** pattern found in the site's sentence => field of activity used in the hook (same key word, so the hook "uses" the proof) */
    private const FIELDS = [
        '/e-?commerce|boutiques? en ligne/iu' => 'la création de sites e-commerce',
        '/applications? mobiles?/iu' => 'le développement d\'applications mobiles',
        '/\berp\b.*m[ée]tiers?|m[ée]tiers?.*\berp\b/iu' => 'les ERP et les logiciels métier',
        '/logiciels? de gestion|gestion commerciale/iu' => 'les logiciels de gestion',
        '/logiciels? m[ée]tiers?|applications? m[ée]tiers?/iu' => 'les logiciels métier',
        '/logiciels? sur mesure|applications? sur mesure/iu' => 'les logiciels sur mesure',
        '/cr[ée]ation de sites?|sites? (internet|web) (sur mesure|vitrines?)|sites? vitrines?/iu' => 'la création de sites internet',
        '/cybers[ée]curit[ée]/iu' => 'la cybersécurité',
        '/infog[ée]rance|maintenance informatique|parcs? informatiques?|r[ée]seaux informatiques/iu' => 'la maintenance informatique',
        '/h[ée]bergement|\bcloud\b/iu' => 'l\'hébergement et le cloud',
        '/intelligence artificielle/iu' => 'l\'intelligence artificielle',
        '/automatisation|automatisme|informatique industrielle/iu' => 'l\'informatique industrielle et l\'automatisation',
        '/(traitement|analyse|exploitation|gestion) (des |de )?donn[ée]es|\bdata\b/iu' => 'l\'exploitation des données',
        '/num[ée]rique/iu' => 'le numérique',
        '/digital/iu' => 'le digital',
        '/informatique/iu' => 'l\'informatique',
    ];

    /** Privacy notices, cookies, legal pages: they talk about "vos données" but say nothing about the company's job. */
    private const LEGAL_TEXT = '/\bcnil\b|rgpd|gdpr|cookies?|donn[ée]es personnelles|mentions l[ée]gales|confidentialit[ée]|droit d.acc[eè]s|vous opposer|rectifier|h[ée]berg[ée] par/iu';

    public function __construct(private HookRules $rules = new HookRules) {}

    public function propose(User $user, Company $company, string $siteText): HookProposal|string
    {
        $best = $this->bestSentence($siteText);
        if ($best === null) {
            // not "irrelevant": this simple engine just could not describe it, the student will
            return 'aucune phrase du site exploitable automatiquement : écris l\'accroche toi-même';
        }

        $experience = $this->closestExperience($user, $siteText);
        if ($experience === null) {
            return 'aucune expérience renseignée dans le profil';
        }

        $proposal = new HookProposal;
        $proposal->relevant = true;
        $proposal->reason = '';
        $proposal->hook = $this->sentence($best['field'], $experience);
        $proposal->excerpt = $best['sentence'];
        $proposal->url = $best['url'];

        return $proposal;
    }

    /**
     * @return array{sentence: string, url: string, field: string}|null
     */
    public function bestSentence(string $siteText): ?array
    {
        $best = null;
        foreach ($this->sections($siteText) as $url => $text) {
            foreach (preg_split('/(?<=[.!?])\s+/u', $text) as $sentence) {
                $sentence = trim($sentence);
                $length = mb_strlen($sentence);
                if ($length < 40 || $length > 200 || str_word_count(Str::ascii($sentence)) < 6 || mb_strtoupper($sentence) === $sentence
                    || preg_match(self::LEGAL_TEXT, $sentence)) {
                    continue;
                }
                $sentenceWords = $this->rules->significantWords($sentence);
                foreach (array_values(self::FIELDS) as $rank => $field) {
                    $pattern = array_keys(self::FIELDS)[$rank];
                    // the field must share a key word with the sentence, otherwise HookVerifier would refuse the hook
                    if (preg_match($pattern, $sentence) && array_intersect($this->rules->significantWords($field), $sentenceWords)) {
                        // a more specific field (earlier in the list) wins, then the first page (home)
                        if ($best === null || $rank < $best['rank']) {
                            $best = ['sentence' => $sentence, 'url' => $url, 'field' => $field, 'rank' => $rank];
                        }
                        break;
                    }
                }
            }
        }

        return $best ? array_diff_key($best, ['rank' => true]) : null;
    }

    public function closestExperience(User $user, string $siteText): ?Experience
    {
        $siteWords = array_flip($this->words($siteText));

        return $user->experiences
            ->sortByDesc(fn (Experience $e) => [
                count(array_filter($this->words($e->title.' '.$e->description.' '.$e->technologies), fn ($w) => isset($siteWords[$w]))),
                $e->in_production,
                $e->ended_at->timestamp ?? PHP_INT_MAX,
            ])
            ->first();
    }

    public function sentence(string $field, Experience $experience): string
    {
        $what = 'mon expérience « '.Str::lower(Str::limit($experience->title, 60, '')).' »';
        if ($experience->organization) {
            $what .= ' chez '.$experience->organization;
        }
        if ($experience->technologies) {
            $what .= ' ('.Str::limit($experience->technologies, 60, '').')';
        }
        $work = $experience->role === ExperienceRole::Team ? 'en équipe' : 'en autonomie';

        return "Votre activité dans {$field} m'intéresse particulièrement, et j'aimerais y mettre en pratique ce que j'ai appris en travaillant {$work} lors de {$what}.";
    }

    /** @return array<string, string> url => text, from the "=== URL : ... ===" sections */
    private function sections(string $siteText): array
    {
        preg_match_all('/=== URL : (\S+) ===\n(.*?)(?=\n\n=== URL : |\z)/s', $siteText, $matches, PREG_SET_ORDER);
        $sections = [];
        foreach ($matches as $match) {
            $sections[$match[1]] = $match[2];
        }

        return $sections;
    }

    private function words(string $text): array
    {
        preg_match_all('/[a-z0-9]{4,}/', strtolower(Str::ascii($text)), $matches);

        return array_values(array_unique($matches[0]));
    }
}
