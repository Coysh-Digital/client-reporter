<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Icons used throughout the admin UI, stored as plain path data and rendered
 * via the <x-icon> Blade component — no icon-font runtime ships to the browser.
 * Source icons are Heroicons v2 (MIT); see resources/icons/README.md.
 */
class Icons
{
    /** @var array<string, array{width: int, height: int, paths: array<int, array{d: string, evenodd: bool}>}>|null */
    private static ?array $icons = null;

    /**
     * @return array{width: int, height: int, paths: array<int, array{d: string, evenodd: bool}>}|null
     */
    public static function get(string $name): ?array
    {
        self::$icons ??= require resource_path('icons/ui.php');

        return self::$icons[$name] ?? null;
    }
}
