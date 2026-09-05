<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resolves a data provider's bundled logo to an inline data-URI, so a report
 * section can show the source as its icon rather than its name. Returns null
 * when there is no bundled logo (e.g. a custom integration), letting the caller
 * fall back to the plain name.
 */
class ProviderLogos
{
    /** Display names whose logo file isn't just a slug of the name. */
    private const ALIASES = [
        'google analytics 4' => 'google_analytics',
        'google analytics' => 'google_analytics',
        'google search console' => 'google_search_console',
        'google ads' => 'google_ads',
        'better uptime' => 'better_uptime',
        'uptime kuma' => 'uptime_kuma',
        'craft commerce' => 'craft_commerce',
    ];

    public static function dataUri(?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $slug = self::ALIASES[mb_strtolower($name)]
            ?? trim((string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($name)), '_');

        $path = public_path('vendor/logos/'.$slug.'.svg');
        if ($slug === '' || ! is_file($path)) {
            return null;
        }

        $svg = @file_get_contents($path);

        return $svg === false ? null : 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
