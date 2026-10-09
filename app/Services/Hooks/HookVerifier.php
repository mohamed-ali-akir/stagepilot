<?php

namespace App\Services\Hooks;

use App\Models\Company;
use App\Services\Web\PageFetcher;

/**
 * The anti-invention safeguard: a hook is only accepted if the excerpt it relies on
 * is found, word for word, on a page of the company's own website.
 */
class HookVerifier
{
    public function __construct(private HookRules $rules, private PageFetcher $fetcher) {}

    /**
     * @return list<string> problems (empty = accepted)
     */
    public function check(Company $company, string $hook, string $excerpt, string $url): array
    {
        $problems = $this->rules->problems($hook, $company->name);

        if (mb_strlen(PageFetcher::normalize($excerpt)) < 25) {
            $problems[] = 'extrait du site trop court ou absent (preuve obligatoire)';

            return $problems;
        }
        if (! $company->website || ! $this->sameSite($url, $company->website)) {
            $problems[] = 'la preuve doit venir du site de l\'entreprise';

            return $problems;
        }

        $page = PageFetcher::visibleText($this->fetcher->fetch($url)['html']);
        if (! str_contains(PageFetcher::normalize($page), PageFetcher::normalize($excerpt))) {
            $problems[] = 'extrait introuvable sur la page : accroche peut-être inventée';
        } elseif (! array_intersect($this->rules->significantWords($hook), $this->rules->significantWords($excerpt))) {
            $problems[] = 'l\'accroche ne reprend rien de l\'extrait du site';
        }

        return $problems;
    }

    public function sameSite(string $url, string $website): bool
    {
        $root = function (string $u) {
            $host = strtolower((string) parse_url(str_contains($u, '://') ? $u : 'https://'.$u, PHP_URL_HOST));
            $parts = explode('.', str_replace('www.', '', $host));

            return implode('.', array_slice($parts, -2));
        };

        return $root($url) !== '' && $root($url) === $root($website);
    }
}
