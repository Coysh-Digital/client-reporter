<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * The report language dictionary. Every fixed word or phrase that appears in a
 * client-facing report — section headings, metric labels, chart titles, legend
 * text, empty-state sentences — is resolved through here, so an agency can
 * reword or translate the entire report without editing any core file.
 *
 * Two layers are deep-merged:
 *
 *   1. The shipped defaults in `config/report-language.php` — tracked in git and
 *      kept current by Client Reporter. Never edit these to customise wording;
 *      an update would overwrite them.
 *   2. An optional agency override at `config/report-language.local.php`, which
 *      is git-ignored so it survives updates. Only the keys it sets are
 *      replaced; anything it omits — including strings introduced by a later
 *      update — falls back to the shipped default. That means a partial
 *      override file never goes stale: new report text keeps working in the
 *      shipped language until the agency chooses to translate it.
 *
 * Keys are dot-notated (`traffic.heading`, `uptime.legend.healthy`). Dynamic
 * values are injected with Laravel-style `:name` placeholders.
 */
class ReportLang
{
    /** @var array<string, mixed>|null Memoised merged dictionary for the request. */
    private static ?array $strings = null;

    /** Override file path; null uses the default location. Set only by tests. */
    private static ?string $localPath = null;

    /**
     * Resolve a dotted key to its string, substituting any `:name` placeholders.
     *
     * @param  array<string, string|int|float>  $replace  placeholder => value
     * @param  string|null  $default  returned when the key is unknown (defaults
     *                                to the key itself, which only surfaces on a
     *                                developer typo since defaults ship complete)
     */
    public static function get(string $key, array $replace = [], ?string $default = null): string
    {
        $value = Arr::get(self::strings(), $key);
        if (! is_string($value)) {
            $value = $default ?? $key;
        }

        foreach ($replace as $search => $replacement) {
            $value = str_replace(':'.$search, (string) $replacement, $value);
        }

        return $value;
    }

    /**
     * The full merged dictionary (defaults with the local override applied).
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::strings();
    }

    /**
     * Drop the memoised dictionary so the next call reloads it. Used by tests
     * that swap the override file at runtime.
     */
    public static function flush(): void
    {
        self::$strings = null;
    }

    /**
     * Point the loader at a specific override file (or null for the default).
     *
     * @internal Test seam only; production always uses the default location.
     */
    public static function setLocalPath(?string $path): void
    {
        self::$localPath = $path;
        self::flush();
    }

    /** @return array<string, mixed> */
    private static function strings(): array
    {
        if (self::$strings !== null) {
            return self::$strings;
        }

        /** @var array<string, mixed> $defaults */
        $defaults = (array) config('report-language', []);

        // The override is required directly (not read through config()) so an
        // agency's edits take effect immediately, even when config is cached.
        $override = [];
        $localPath = self::$localPath ?? config_path('report-language.local.php');
        if (is_file($localPath)) {
            $loaded = require $localPath;
            $override = is_array($loaded) ? $loaded : [];
        }

        return self::$strings = self::deepMerge($defaults, $override);
    }

    /**
     * Recursively merge the override over the defaults: a leaf present in the
     * override wins, while any key the override omits keeps its default. Numeric
     * lists are replaced wholesale rather than concatenated.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private static function deepMerge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && ! array_is_list($value)) {
                $base[$key] = self::deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
