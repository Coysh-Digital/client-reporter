<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Fetches and caches a site's favicon directly from the site itself (no
 * third-party favicon service, so a client's domain is never handed to Google).
 * Parses the homepage for a declared icon <link>, falling back to /favicon.ico,
 * and stores the image on the public disk. Best-effort: any failure leaves the
 * previously cached icon in place and just records the attempt time.
 */
class SiteFaviconFetcher
{
    private const MAX_BYTES = 512 * 1024;

    private const TIMEOUT = 15;

    /** @var array<string, string> */
    private const EXTENSIONS = [
        'image/png' => 'png',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
        'image/svg+xml' => 'svg',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function fetch(Site $site): bool
    {
        $parts = parse_url($site->url);
        if (empty($parts['host'])) {
            return false;
        }
        $origin = ($parts['scheme'] ?? 'https').'://'.$parts['host'];

        try {
            $iconUrl = $this->discoverIconUrl($site->url, $origin);
            $response = Http::timeout(self::TIMEOUT)->get($iconUrl);

            $ext = $this->extensionFor((string) $response->header('Content-Type'), $iconUrl);
            $body = $response->body();

            if (! $response->successful() || $ext === null || $body === '' || strlen($body) > self::MAX_BYTES) {
                $site->forceFill(['favicon_fetched_at' => now()])->save();

                return false;
            }

            $path = 'site-favicons/'.$site->id.'.'.$ext;
            Storage::disk('public')->put($path, $body);

            $site->forceFill(['favicon_path' => $path, 'favicon_fetched_at' => now()])->save();

            return true;
        } catch (Throwable) {
            // Record the attempt so a persistently failing site isn't retried
            // every run, and keep any icon already cached.
            $site->forceFill(['favicon_fetched_at' => now()])->save();

            return false;
        }
    }

    private function discoverIconUrl(string $pageUrl, string $origin): string
    {
        try {
            $html = Http::timeout(self::TIMEOUT)->get($pageUrl)->body();
        } catch (Throwable) {
            $html = '';
        }

        if ($html !== '' && preg_match_all('/<link\b[^>]*>/i', $html, $tags)) {
            $fallback = null;

            foreach ($tags[0] as $tag) {
                if (! preg_match('/\brel\s*=\s*["\']([^"\']*)["\']/i', $tag, $rel) || ! str_contains(strtolower($rel[1]), 'icon')) {
                    continue;
                }
                if (! preg_match('/\bhref\s*=\s*["\']([^"\']+)["\']/i', $tag, $href)) {
                    continue;
                }

                $url = $this->absolutise(trim($href[1]), $origin, $pageUrl);
                if ($url === null) {
                    continue;
                }

                // SVG icons scale cleanly, so prefer one if declared.
                if (str_contains(strtolower($url), '.svg')) {
                    return $url;
                }
                $fallback ??= $url;
            }

            if ($fallback !== null) {
                return $fallback;
            }
        }

        return $origin.'/favicon.ico';
    }

    private function absolutise(string $href, string $origin, string $pageUrl): ?string
    {
        if ($href === '' || str_starts_with($href, 'data:')) {
            return null;
        }
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            return (parse_url($origin, PHP_URL_SCHEME) ?: 'https').':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        return rtrim($pageUrl, '/').'/'.$href;
    }

    private function extensionFor(string $contentType, string $url): ?string
    {
        $type = strtolower(trim(explode(';', $contentType)[0]));
        if (isset(self::EXTENSIONS[$type])) {
            return self::EXTENSIONS[$type];
        }

        // Fall back to the URL's extension only for known image types — never
        // save an HTML error page as if it were an icon.
        $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($ext, ['png', 'ico', 'svg', 'jpg', 'jpeg', 'gif', 'webp'], true)
            ? ($ext === 'jpeg' ? 'jpg' : $ext)
            : null;
    }
}
