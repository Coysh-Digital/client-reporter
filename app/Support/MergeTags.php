<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Report;
use App\Support\Branding\ResolvedBranding;

/**
 * Replaces merge tags in a report's free text with the report's real values, so
 * an agency can write "Hi {{ client }}," once in a template and have it read the
 * client's name in every generated report. Tags are case-insensitive and
 * tolerate surrounding whitespace ({{client}} or {{ Client }}); anything that
 * isn't a known tag is left exactly as written.
 */
class MergeTags
{
    /**
     * The available tags, for help text and documentation.
     *
     * @return array<string, string>
     */
    public static function available(): array
    {
        return [
            'client' => "The client's name",
            'contact' => "The client's contact name (falls back to the client name)",
            'site' => "The site's name",
            'period' => 'The reporting period (e.g. 1–31 August 2026)',
            'agency' => 'Your agency name',
        ];
    }

    public static function apply(?string $text, Report $report, ResolvedBranding $branding): ?string
    {
        if ($text === null || ! str_contains($text, '{{')) {
            return $text;
        }

        $report->loadMissing('site.client');
        $client = $report->site->client;

        $values = [
            'client' => $client->name,
            'contact' => $client->contact_name ?: $client->name,
            'site' => $report->site->name,
            'period' => $report->dateRange()->label(),
            'agency' => $branding->agencyName,
        ];

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z_]+)\s*\}\}/',
            fn (array $matches): string => $values[strtolower($matches[1])] ?? $matches[0],
            $text,
        ) ?? $text;
    }
}
