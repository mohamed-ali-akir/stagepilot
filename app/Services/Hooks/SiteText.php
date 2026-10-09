<?php

namespace App\Services\Hooks;

use App\Services\Web\PageFetcher;

/**
 * Collects what a company says about itself (home page + about / products / services pages),
 * each section labelled with its URL so the AI can quote it and say where the quote comes from.
 */
class SiteText
{
    private const PRESENTATION_PAGES = '/propos|about|qui-sommes|societe|entreprise|agence|solution|produit|logiciel|services|expertise|metier|savoir|activit|offre|realisation|references/i';

    public function __construct(private PageFetcher $fetcher) {}

    public function collect(string $website, int $maxPages = 4, int $maxCharsPerPage = 3500): string
    {
        $home = $this->fetcher->fetch($website);
        if ($home['html'] === '') {
            return '';
        }

        $urls = array_merge([$home['url']], PageFetcher::sameSiteLinks($home['html'], $home['url'], self::PRESENTATION_PAGES));
        $sections = [];
        foreach (array_slice(array_values(array_unique($urls)), 0, $maxPages) as $url) {
            $text = PageFetcher::visibleText($this->fetcher->fetch($url)['html']);
            if ($text !== '') {
                $sections[] = "=== URL : $url ===\n".mb_substr($text, 0, $maxCharsPerPage);
            }
        }

        return implode("\n\n", $sections);
    }
}
