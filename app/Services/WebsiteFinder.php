<?php

namespace App\Services;

use App\Models\Company;
use App\Services\Web\PageFetcher;
use Illuminate\Support\Str;

/**
 * Finds a company's website by trying likely domain names (name.fr, name.com...).
 * A site is kept ONLY if it shows the company's SIREN, or its postal code + city + a word
 * of its name: this avoids picking the website of a namesake.
 */
class WebsiteFinder
{
    private const LEGAL_WORDS = ['sarl', 'sas', 'sasu', 'eurl', 'sa', 'sci', 'snc', 'scop', 'societe', 'ste', 'ets',
        'etablissements', 'groupe', 'group', 'france', 'the', 'le', 'la', 'les', 'de', 'du', 'des', 'et'];

    private const PARKED = '/domain (name )?is for sale|ce domaine est [àa] vendre|hugedomains|godaddy|sedo\.com|domaine a [ée]t[ée] enregistr[ée]|site en construction|under construction|index of \/|coming soon|bient[ôo]t disponible/i';

    private const LEGAL_PAGES = '/mention|legal|l%C3%A9gal|contact|propos|about|cgv|cgu|societe|qui-sommes/i';

    public function __construct(private PageFetcher $fetcher) {}

    /**
     * @return array{url: string, proof: string}|null
     */
    public function find(Company $company): ?array
    {
        foreach ($this->candidateDomains($company->name) as $domain) {
            if ($found = $this->verify('https://'.$domain, $company)) {
                return $found;
            }
        }

        return null;
    }

    /** "Dlm Soft (Developp Logiciel Metier)" -> dlmsoft.fr, dlmsoft.com, dlm-soft.fr... */
    public function candidateDomains(string $name): array
    {
        $words = $this->nameWords($name);
        if (! $words) {
            return [];
        }
        $bases = [implode('', $words), implode('-', $words)];
        if (strlen($words[0]) >= 4) {
            $bases[] = $words[0];
        }
        if (count($words) >= 2 && strlen($words[0].$words[1]) >= 5) {
            $bases[] = $words[0].$words[1];
        }

        $domains = [];
        foreach (array_unique($bases) as $base) {
            if (strlen($base) >= 3) {
                $domains[] = $base.'.fr';
                $domains[] = $base.'.com';
            }
        }
        $domains[] = $bases[0].'.net';
        $domains[] = $bases[0].'.eu';
        $domains[] = $bases[0].'.io';

        return array_slice(array_values(array_unique($domains)), 0, 9);
    }

    /**
     * @return array{url: string, proof: string}|null
     */
    public function verify(string $url, Company $company): ?array
    {
        $home = $this->fetcher->fetch($url);
        $text = PageFetcher::visibleText($home['html']);
        if ($text === '' || (preg_match(self::PARKED, Str::limit($text, 3000)) && strlen($text) < 3000)) {
            return null;
        }

        $pages = [$text];
        $links = PageFetcher::sameSiteLinks($home['html'], $home['url'], self::LEGAL_PAGES);
        foreach (['mentions-legales', 'mentions-legales/', 'contact'] as $path) {
            $links[] = rtrim($home['url'], '/').'/'.$path;
        }
        foreach (array_slice(array_unique($links), 0, 6) as $link) {
            $pages[] = PageFetcher::visibleText($this->fetcher->fetch($link)['html']);
        }
        $all = implode(' ', $pages);

        $digits = preg_replace('/[\s\x{00a0}.\-]/u', '', $all);
        if (strlen($company->siren) === 9 && str_contains($digits, $company->siren)) {
            return ['url' => $home['url'], 'proof' => 'SIREN affiché sur le site'];
        }

        $normalized = PageFetcher::normalize($all);
        $city = substr(PageFetcher::normalize($company->city), 0, 6);
        $nameWords = array_filter($this->nameWords($company->name), fn ($w) => strlen($w) >= 4);
        $hasName = collect($nameWords)->contains(fn ($w) => str_contains($normalized, $w));
        if ($company->postal_code && str_contains($all, $company->postal_code) && $city && str_contains($normalized, $city) && $hasName) {
            return ['url' => $home['url'], 'proof' => 'adresse (code postal + ville) et nom affichés sur le site'];
        }

        return null;
    }

    /** @return list<string> */
    private function nameWords(string $name): array
    {
        $clean = strtolower(Str::ascii(preg_replace('/\(.*?\)/', ' ', $name)));
        $words = preg_split('/[^a-z0-9]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter($words, fn ($w) => ! in_array($w, self::LEGAL_WORDS, true)));
    }
}
