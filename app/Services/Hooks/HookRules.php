<?php

namespace App\Services\Hooks;

use App\Services\Web\PageFetcher;
use Illuminate\Support\Str;

/**
 * Form and honesty rules for a hook (the one personalised sentence of the application email).
 * Checked when the hook is written AND again right before sending.
 */
class HookRules
{
    /** pattern => reason shown to the student */
    private const FORBIDDEN = [
        '/\bseule?s?\b|à moi seul|tout seul/iu' => 'ne pas dire que tu as travaillé seul',
        '/j\'ai (enti[eè]rement |moi-m[eê]me )?(d[ée]velopp[ée]|cr[ée][ée]|con[cç]u|cod[ée]|r[ée]alis[ée]|programm[ée])/iu' => 'dire « contribué » ou « participé », pas « j\'ai développé / créé / conçu »',
        '/en (d[ée]veloppant|concevant|cr[ée]ant|codant|programmant|r[ée]alisant)\b/iu' => 'dire « contribué » ou « participé », pas « en développant / concevant »',
        '/\bexperte?s?\b|\bmon expertise|je ma[iî]tris|\bconfirm[ée]e?\b|\bsenior|\bchevronn/iu' => 'pas d\'exagération de niveau',
        '/leader|incontournable|excellence|exceptionnel|magnifique|formidable|incroyable|\bmeilleur|n°\s?1|num[ée]ro un|r[ée]volutionn|passionnant|fascin|r[êe]ve/iu' => 'pas de flatterie',
        '/!/' => 'pas de point d\'exclamation',
        '/[—–]/u' => 'pas de tiret cadratin',
        '/[{}<>]/' => 'caractère interdit',
        '/https?:\/\/|www\./i' => 'pas de lien dans l\'accroche',
    ];

    private const STOP_WORDS = ['votre', 'vos', 'vous', 'notre', 'nous', 'dans', 'pour', 'avec', 'sans', 'comme', 'leurs',
        'leur', 'cette', 'ces', 'sont', 'plus', 'tout', 'tous', 'toute', 'toutes', 'entre', 'elles', 'ils', 'elle', 'mais',
        'donc', 'car', 'aussi', 'depuis', 'chez', 'pendant', 'entreprise', 'societe', 'interesse', 'interessent',
        'particulierement', 'beaucoup', 'aimerais', 'travailler', 'travail', 'stage', 'developpement', 'application',
        'applications', 'logiciel', 'logiciels', 'solution', 'solutions'];

    /**
     * @return list<string> problems (empty = the hook is fine)
     */
    public function problems(?string $hook, string $companyName = ''): array
    {
        $hook = trim((string) $hook);
        if ($hook === '') {
            return ['accroche vide'];
        }

        $problems = [];
        $length = mb_strlen($hook);
        if ($length < 60 || $length > 350) {
            $problems[] = "longueur $length (entre 60 et 350 caractères)";
        }
        if (! preg_match('/^\p{Lu}/u', $hook)) {
            $problems[] = 'doit commencer par une majuscule';
        }
        if (! str_ends_with($hook, '.')) {
            $problems[] = 'doit finir par un point';
        }
        if (preg_match('/[.?!;]\s+\S/u', mb_substr($hook, 0, -1))) {
            $problems[] = 'une seule phrase';
        }
        foreach (self::FORBIDDEN as $pattern => $reason) {
            if (preg_match($pattern, $hook)) {
                $problems[] = $reason;
            }
        }
        $mentionsCompany = $companyName !== '' && str_contains(PageFetcher::normalize($hook), substr(PageFetcher::normalize($companyName), 0, 6));
        if (! preg_match('/\b(votre|vos|vous)\b/iu', $hook) && ! $mentionsCompany) {
            $problems[] = 'doit parler de l\'entreprise (votre / vos / son nom)';
        }

        return $problems;
    }

    public function isValid(?string $hook, string $companyName = ''): bool
    {
        return $this->problems($hook, $companyName) === [];
    }

    /** Significant words (5+ letters, no stop words), used to check that the hook really uses the excerpt. */
    public function significantWords(string $text): array
    {
        $ascii = strtolower(Str::ascii($text));
        preg_match_all('/[a-z0-9]{5,}/', $ascii, $matches);

        return array_values(array_diff(array_unique($matches[0]), self::STOP_WORDS));
    }
}
