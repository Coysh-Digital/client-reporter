<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Site;
use App\Support\Http\OutboundUrl;
use App\Support\Http\UnsafeUrlException;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Fetches and caches a site's favicon directly from the site itself (no
 * third-party favicon service, so a client's domain is never handed to Google).
 * Parses the homepage for a declared icon <link>, falling back to /favicon.ico,
 * and stores the image on the public disk. Best-effort: any failure leaves the
 * previously cached icon in place and just records the attempt time.
 *
 * Every fetch goes through the outbound URL guard, and SVG is deliberately not
 * accepted: the icon is served from this application's own origin, so a
 * script-bearing SVG from a client site would run with the app's cookies.
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
            $guard = app(OutboundUrl::class);
            $guard->assertPublic($site->url);

            $iconUrl = $this->discoverIconUrl($guard, $site->url, $origin);
            $response = $guard->client(self::TIMEOUT)->get($iconUrl);

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

    private function discoverIconUrl(OutboundUrl $guard, string $pageUrl, string $origin): string
    {
        try {
            $html = $guard->client(self::TIMEOUT)->get($pageUrl)->body();
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

                // A declared icon may live on any host; it must pass the same
                // guard as the site itself before it is fetched.
                try {
                    $guard->assertPublic($url);
                } catch (UnsafeUrlException) {
                    continue;
                }

                // SVG is not stored (see class docblock); skip declared SVG icons.
                if (str_contains(strtolower($url), '.svg')) {
                    continue;
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

        // A declared type we do not accept (SVG, HTML, JSON…) is final: never
        // save such a body under an image extension borrowed from the URL.
        if ($type !== '' && ! in_array($type, ['application/octet-stream', 'binary/octet-stream'], true)) {
            return null;
        }

        // Fall back to the URL's extension only for known image types — never
        // save an HTML error page as if it were an icon.
        $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($ext, ['png', 'ico', 'jpg', 'jpeg', 'gif', 'webp'], true)
            ? ($ext === 'jpeg' ? 'jpg' : $ext)
            : null;
    }
}
