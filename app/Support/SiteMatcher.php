<?php

declare(strict_types=1);

namespace App\Support;

use App\Integrations\Support\DiscoveredConnection;
use App\Models\Site;
use Illuminate\Support\Collection;

/**
 * Matches provider entities (monitors, properties, domains) to sites by URL,
 * for the workspace connect flow. Comparison is host-based and forgiving of
 * scheme, "www." and trailing paths, so "https://www.acme.com/" lines up with a
 * site stored as "acme.com".
 */
class SiteMatcher
{
    /**
     * Reduce a URL to a comparable host, e.g. "https://www.Acme.com/shop" →
     * "acme.com". Returns '' when there is nothing to compare.
     */
    public static function normalise(?string $url): string
    {
        if ($url === null) {
            return '';
        }

        $host = strtolower(trim($url));
        $host = (string) preg_replace('#^[a-z]+://#', '', $host);
        $host = explode('/', $host)[0];
        $host = (string) preg_replace('#^www\.#', '', $host);

        return trim($host, '.');
    }

    /**
     * Propose a site id for each discovered entity (by array index), or null
     * when no site's host matches.
     *
     * @param  array<int, DiscoveredConnection>  $discovered
     * @param  Collection<int, Site>  $sites
     * @return array<int, int|null>
     */
    public static function match(array $discovered, Collection $sites): array
    {
        $byHost = [];
        foreach ($sites as $site) {
            $host = self::normalise($site->url);
            if ($host !== '' && ! isset($byHost[$host])) {
                $byHost[$host] = $site->id;
            }
        }

        $out = [];
        foreach ($discovered as $index => $entity) {
            $host = self::normalise($entity->url);
            $out[$index] = $host !== '' ? ($byHost[$host] ?? null) : null;
        }

        return $out;
    }
}
