<?php

namespace App\Services\Web;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Downloads web pages like a normal browser would, without ever throwing:
 * an unreachable page simply gives an empty result.
 */
class PageFetcher
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    /** Pages already downloaded during this request (the same page is often needed twice). */
    private array $cache = [];

    /**
     * @return array{url: string, html: string} final URL after redirects, and the HTML ('' if it failed)
     */
    public function fetch(string $url): array
    {
        if (! str_starts_with($url, 'http')) {
            $url = 'https://'.$url;
        }
        if (isset($this->cache[$url])) {
            return $this->cache[$url];
        }

        $result = ['url' => $url, 'html' => ''];
        foreach ([true, false] as $verifySsl) {
            try {
                $request = Http::withHeaders(['User-Agent' => self::USER_AGENT, 'Accept-Language' => 'fr-FR,fr;q=0.9'])
                    ->timeout(12)
                    ->connectTimeout(8);
                if (! $verifySsl) {
                    $request = $request->withoutVerifying();
                }
                $response = $request->get($url);
                $finalUrl = (string) ($response->effectiveUri() ?? $url);
                $result = ['url' => $finalUrl, 'html' => $response->successful() ? $response->body() : ''];
                break;
            } catch (ConnectionException $e) {
                if ($verifySsl && str_contains(strtolower($e->getMessage()), 'ssl')) {
                    continue;
                }
                break;
            } catch (\Throwable) {
                break;
            }
        }

        return $this->cache[$url] = $result;
    }

    /** Visible text of a page (no scripts, styles or tags). */
    public static function visibleText(string $html): string
    {
        $html = preg_replace('#<(script|style|noscript|svg)[^>]*>.*?</\1>#si', ' ', $html) ?? '';
        $text = html_entity_decode(strip_tags(preg_replace('/<[^>]+>/', ' $0 ', $html) ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * Links of the page that stay on the same site and whose URL matches the pattern.
     *
     * @return list<string>
     */
    public static function sameSiteLinks(string $html, string $baseUrl, string $pattern): array
    {
        $host = parse_url($baseUrl, PHP_URL_HOST);
        preg_match_all('/href=["\']([^"\'#]+)["\']/i', $html, $matches);
        $links = [];
        foreach ($matches[1] as $href) {
            $absolute = self::absoluteUrl(trim($href), $baseUrl);
            if ($absolute && parse_url($absolute, PHP_URL_HOST) === $host && preg_match($pattern, $absolute)) {
                $links[$absolute] = true;
            }
        }

        return array_keys($links);
    }

    public static function absoluteUrl(string $href, string $base): ?string
    {
        if (preg_match('#^(mailto|tel|javascript):#i', $href)) {
            return null;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $parts = parse_url($base);
        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $root = $parts['scheme'].'://'.$parts['host'];
        if (str_starts_with($href, '//')) {
            return $parts['scheme'].':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $root.$href;
        }
        $dir = isset($parts['path']) ? preg_replace('#/[^/]*$#', '/', $parts['path']) : '/';

        return $root.$dir.$href;
    }

    /** "Kiwo S.A.S" -> "kiwosas" : lower case, no accents, letters and digits only. */
    public static function normalize(?string $text): string
    {
        $ascii = \Illuminate\Support\Str::ascii((string) $text);

        return preg_replace('/[^a-z0-9]/', '', strtolower($ascii)) ?? '';
    }
}
