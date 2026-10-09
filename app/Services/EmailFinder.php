<?php

namespace App\Services;

use App\Services\Web\PageFetcher;

/**
 * Reads a company website (home + contact / legal / about / careers pages) and picks the best email.
 *
 * Avoids the mistakes seen on real sites: form placeholders (nom.prenom@monemail.com),
 * the web host's or web agency's address, DPO / no-reply addresses, and fake phone numbers.
 */
class EmailFinder
{
    private const LINK_PATTERN = '/contact|mention|legal|l%C3%A9gal|propos|about|recrut|carri|emploi|job|equipe|team|societe|qui-sommes|rejoindre/i';

    private const FALLBACK_PAGES = ['contact', 'contact/', 'nous-contacter', 'contact.html', 'mentions-legales', 'mentions-legales/', 'mentions-legales.html'];

    private const FAKE_DOMAINS = ['example.com', 'example.fr', 'exemple.com', 'exemple.fr', 'domaine.com', 'domaine.fr',
        'domain.com', 'monemail.com', 'mail.com', 'email.com', 'votredomaine.com', 'votre-domaine.fr', 'votredomaine.fr',
        'test.com', 'sentry.io', 'wixpress.com', 'site.com', 'societe.com', 'entreprise.com', 'mondomaine.com', 'mondomaine.fr'];

    private const FAKE_LOCALS = ['nom.prenom', 'prenom.nom', 'email', 'votre.email', 'votreemail', 'votremail', 'vous',
        'your', 'name', 'john.doe', 'jean.dupont', 'adresse', 'user', 'nom', 'prenom', 'votre-email', 'monemail',
        'mon.email', 'test', 'exemple', 'example'];

    private const EXCLUDED_LOCALS = '/^(dpo|rgpd|gdpr|privacy|cnil|abuse|no-?reply|donotreply|postmaster|mailer-daemon|hostmaster|unsubscribe|desinscription|newsletter)/i';

    private const PROVIDERS = ['gmail.com', 'hotmail.com', 'hotmail.fr', 'outlook.com', 'outlook.fr', 'live.fr', 'yahoo.fr',
        'yahoo.com', 'orange.fr', 'wanadoo.fr', 'free.fr', 'sfr.fr', 'laposte.net', 'icloud.com', 'gmx.fr'];

    private const HOST_CONTEXT = '/h[ée]berg|hosted|r[ée]alis[ée] par|con[cç]u par|cr[ée]ation du site|webmaster|agence web|d[ée]velopp[ée] par|design par/iu';

    private const PHONE = '/(?<![\d.])(?:\+33\s?\(0\)\s?|\+33\s?|0)[1-9](?:[\s.\-]\d{2}){4}(?!\d)/';

    public function __construct(private PageFetcher $fetcher) {}

    /**
     * @return array{email: ?string, phone: ?string, notes: list<string>}
     */
    public function find(string $website, string $companyName): array
    {
        $home = $this->fetcher->fetch($website);
        if ($home['html'] === '') {
            return ['email' => null, 'phone' => null, 'notes' => ['site injoignable']];
        }
        $host = (string) parse_url($home['url'], PHP_URL_HOST);

        $links = PageFetcher::sameSiteLinks($home['html'], $home['url'], self::LINK_PATTERN);
        foreach (self::FALLBACK_PAGES as $path) {
            $links[] = rtrim($home['url'], '/').'/'.$path;
        }
        $pages = [$home['url'] => $home['html']];
        foreach (array_slice(array_values(array_unique($links)), 0, 12) as $link) {
            $pages[$link] ??= $this->fetcher->fetch($link)['html'];
        }

        $candidates = [];
        $pageCount = [];
        $phones = [];
        foreach ($pages as $html) {
            if ($html === '') {
                continue;
            }
            $found = $this->emailsIn($html);
            $personal = count(array_filter($found, fn ($ctx, $email) => preg_match('/^[a-z]+[._-][a-z]+@/', $email), ARRAY_FILTER_USE_BOTH));
            foreach ($found as $email => $context) {
                $score = $this->score($email, $context, $host, $companyName);
                if ($score === null) {
                    continue;
                }
                $pageCount[$email] = ($pageCount[$email] ?? 0) + 1;
                // a personal address taken from a staff directory (many names on one page) is a poor contact
                $points = $score['points'] - ($personal > 5 && in_array('adresse nominative', $score['notes'], true) ? 30 : 0);
                if (! isset($candidates[$email]) || $points > $candidates[$email]['points']) {
                    $candidates[$email] = ['points' => $points, 'notes' => $score['notes']];
                }
            }
            $phones = array_merge($phones, $this->phonesIn($html));
        }

        // an address shown on several pages (header, contact, legal notice) is the official one
        foreach ($candidates as $email => $candidate) {
            $candidates[$email]['points'] += 15 * min($pageCount[$email] - 1, 3);
        }

        $email = null;
        $notes = [];
        if ($candidates) {
            uasort($candidates, fn ($a, $b) => $b['points'] <=> $a['points']);
            $email = array_key_first($candidates);
            $notes = $candidates[$email]['notes'];
        } elseif (strlen(PageFetcher::visibleText($home['html'])) < 300) {
            $notes[] = 'site en JavaScript, à vérifier à la main';
        } else {
            $notes[] = 'aucun email trouvé sur le site (formulaire de contact ?)';
        }

        $phone = null;
        if ($phones) {
            $counts = array_count_values($phones);
            arsort($counts);
            $phone = array_key_first($counts);
        }

        return ['email' => $email, 'phone' => $phone, 'notes' => $notes];
    }

    /**
     * @return array<string, string> email => text just before it on the page
     */
    public function emailsIn(string $html): array
    {
        $raw = html_entity_decode(urldecode($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $raw = preg_replace('/\s*[\[(]\s*(at|arobase)\s*[\])]\s*/i', '@', $raw);
        $raw = preg_replace('/\s*[\[(]\s*(dot|point)\s*[\])]\s*/i', '.', $raw);
        $text = PageFetcher::visibleText($raw);

        preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}/', $raw, $matches);
        $emails = array_merge($matches[0], $this->cloudflareEmails($html));

        $result = [];
        foreach ($emails as $email) {
            $email = strtolower(trim($email, '.'));
            $position = stripos($text, $email);
            $result[$email] = $position === false ? '' : substr($text, max(0, $position - 200), min(200, $position));
        }

        return $result;
    }

    /** Cloudflare hides emails behind data-cfemail="..." (XOR with the first byte). */
    private function cloudflareEmails(string $html): array
    {
        preg_match_all('/data-cfemail="([0-9a-fA-F]+)"/', $html, $matches);

        return array_map(function ($code) {
            $key = hexdec(substr($code, 0, 2));
            $email = '';
            for ($i = 2; $i < strlen($code); $i += 2) {
                $email .= chr(hexdec(substr($code, $i, 2)) ^ $key);
            }

            return $email;
        }, $matches[1]);
    }

    public function isFake(string $email): bool
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return in_array($domain, self::FAKE_DOMAINS, true)
            || str_contains($domain, 'sentry')
            || in_array($local, self::FAKE_LOCALS, true)
            || preg_match('/\.(png|jpe?g|gif|webp|svg|css|js)$/i', $email)
            || preg_match(self::EXCLUDED_LOCALS, $local);
    }

    /** True if the email domain belongs to the company (bee2link.fr for bee2linkgroup.com, vertech.fr for vertech.eu...). */
    public function relatedDomain(string $emailDomain, string $siteHost, string $companyName): bool
    {
        $label = fn (string $domain) => (fn ($parts) => $parts[max(0, count($parts) - 2)])(explode('.', str_replace('www.', '', strtolower($domain))));
        $e = $label($emailDomain);
        $s = $label($siteHost);
        if ($e === $s) {
            return true;
        }
        if (strlen($e) >= 4 && (str_contains($s, $e) || str_contains($e, $s))) {
            return true;
        }

        return strlen($e) >= 5 && str_contains(PageFetcher::normalize($companyName), $e);
    }

    /**
     * @return array{points: int, notes: list<string>}|null null = reject this address
     */
    public function score(string $email, string $context, string $siteHost, string $companyName): ?array
    {
        if ($this->isFake($email)) {
            return null;
        }
        [$local, $domain] = explode('@', $email, 2);
        $related = $this->relatedDomain($domain, $siteHost, $companyName);
        if (! $related && preg_match(self::HOST_CONTEXT, $context)) {
            return null; // address of the web host or of the agency that built the site
        }

        $notes = [];
        if ($related) {
            $points = 100;
        } elseif (in_array($domain, self::PROVIDERS, true)) {
            $points = 10;
            $notes[] = 'email à vérifier (adresse Gmail/Orange… et pas au nom de l\'entreprise)';
        } else {
            $points = 0;
            $notes[] = "email à vérifier (domaine $domain différent du site)";
        }

        if (preg_match('/^(recrut|rh|jobs?|emploi|stages?|candidat|carriere)/', $local)) {
            $points += 50;
        } elseif (str_starts_with($local, 'contact')) {
            $points += 40;
        } elseif (preg_match('/^(info|bonjour|hello|accueil|secretariat|direction|agence|service|equipe|team)/', $local)) {
            $points += 30;
        } elseif (preg_match('/^(commerc|sales|vente|devis)/', $local) || str_contains($local, 'sales')) {
            $points += 5;
            $notes[] = 'adresse commerciale';
        } elseif (preg_match('/^(admin|webmaster|support|sav|compta|factur|technique|hotline)/', $local)) {
            $points += 2;
        } elseif (preg_match('/^[a-z]+[._-][a-z]+$/', $local)) {
            $points += 20;
            $notes[] = 'adresse nominative';
        } else {
            $points += 10;
        }

        return ['points' => $points, 'notes' => $notes];
    }

    /** @return list<string> phone numbers formatted "03 85 46 01 50" */
    public function phonesIn(string $html): array
    {
        $numbers = [];
        preg_match_all('/href=["\']tel:([^"\']+)/i', $html, $links);
        foreach ($links[1] as $tel) {
            $numbers[] = preg_replace('/\D/', '', $tel);
        }
        preg_match_all(self::PHONE, PageFetcher::visibleText($html), $texts);
        foreach ($texts[0] as $tel) {
            $numbers[] = preg_replace('/\D/', '', $tel);
        }

        $formatted = [];
        foreach ($numbers as $digits) {
            if (str_starts_with($digits, '33') && strlen($digits) === 11) {
                $digits = '0'.substr($digits, 2);
            }
            if (strlen($digits) === 10 && $digits[0] === '0' && $digits[1] !== '0') {
                $formatted[] = implode(' ', str_split($digits, 2));
            }
        }

        return $formatted;
    }
}
