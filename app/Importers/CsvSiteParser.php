<?php

declare(strict_types=1);

namespace App\Importers;

/**
 * Parses a CSV export of sites (for example from a platform with no API of its
 * own) into normalised {@see ImportedSite} rows. A header row is required; its
 * columns are matched by name against a set of accepted aliases, in any order.
 *
 * Accepted columns: url (required), name, client, cms.
 */
final class CsvSiteParser
{
    /**
     * Header aliases, lower-cased, mapped to the canonical field.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'url' => 'url',
        'website' => 'url',
        'site_url' => 'url',
        'site url' => 'url',
        'domain' => 'url',
        'address' => 'url',
        'name' => 'name',
        'site' => 'name',
        'site_name' => 'name',
        'site name' => 'name',
        'title' => 'name',
        'client' => 'client',
        'client_name' => 'client',
        'client name' => 'client',
        'company' => 'client',
        'cms' => 'cms',
        'cms_type' => 'cms',
        'cms type' => 'cms',
        'platform' => 'cms',
    ];

    /**
     * @return array{sites: array<int, ImportedSite>, skipped: int}
     *
     * @throws ImporterException
     */
    public static function parse(string $contents): array
    {
        // Strip a leading UTF-8 BOM that spreadsheet exports often add.
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        if (trim($contents) === '') {
            throw new ImporterException('That file was empty.');
        }

        $delimiter = self::sniffDelimiter($contents);

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new ImporterException('The file could not be read.');
        }
        fwrite($handle, $contents);
        rewind($handle);

        $header = fgetcsv($handle, separator: $delimiter, escape: '');
        if ($header === false || $header === [null]) {
            fclose($handle);
            throw new ImporterException('That file had no header row.');
        }

        $columns = self::mapColumns($header);
        if (! isset($columns['url'])) {
            fclose($handle);
            throw new ImporterException('The file needs a "url" column (a header row with at least url, and optionally name, client and cms).');
        }

        $sites = [];
        $skipped = 0;

        while (($record = fgetcsv($handle, separator: $delimiter, escape: '')) !== false) {
            if ($record === [null]) {
                continue; // blank line
            }

            $url = self::normaliseUrl(self::cell($record, $columns['url']));
            if ($url === null) {
                $skipped++;

                continue;
            }

            $name = self::cell($record, $columns['name'] ?? null);
            $client = self::cell($record, $columns['client'] ?? null);
            $cms = self::normaliseCms(self::cell($record, $columns['cms'] ?? null));

            $sites[] = new ImportedSite(
                externalId: $url,
                name: $name !== '' ? $name : (string) (parse_url($url, PHP_URL_HOST) ?: $url),
                url: $url,
                cmsType: $cms,
                suggestedClient: $client !== '' ? $client : null,
            );
        }

        fclose($handle);

        return ['sites' => $sites, 'skipped' => $skipped];
    }

    /**
     * Pick the delimiter from the header line: comma unless semicolons or tabs
     * clearly outnumber them (common in European locales and spreadsheet exports).
     */
    private static function sniffDelimiter(string $contents): string
    {
        $firstLine = strtok($contents, "\r\n");
        if ($firstLine === false) {
            return ',';
        }

        $counts = [
            ',' => substr_count($firstLine, ','),
            ';' => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
        ];
        arsort($counts);
        $delimiter = (string) array_key_first($counts);

        return $counts[$delimiter] > 0 ? $delimiter : ',';
    }

    /**
     * @param  array<int, string|null>  $header
     * @return array<string, int>
     */
    private static function mapColumns(array $header): array
    {
        $columns = [];
        foreach ($header as $index => $label) {
            $key = mb_strtolower(trim((string) $label));
            $canonical = self::ALIASES[$key] ?? null;
            if ($canonical !== null && ! isset($columns[$canonical])) {
                $columns[$canonical] = $index;
            }
        }

        return $columns;
    }

    /**
     * @param  array<int, string|null>  $record
     */
    private static function cell(array $record, ?int $index): string
    {
        if ($index === null) {
            return '';
        }

        return trim((string) ($record[$index] ?? ''));
    }

    /**
     * Return a valid http(s) URL, prefixing a bare host with https://, or null
     * when the cell can't be made into one.
     */
    private static function normaliseUrl(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $value)) {
            $value = 'https://'.$value;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = mb_strtolower((string) (parse_url($value, PHP_URL_SCHEME) ?: ''));
        if (! in_array($scheme, ['http', 'https'], true) || (parse_url($value, PHP_URL_HOST) ?: '') === '') {
            return null;
        }

        return $value;
    }

    /**
     * Constrain the CMS to the values a Site understands; anything else (or a
     * blank cell) becomes an empty string, letting the import leave it unset.
     */
    private static function normaliseCms(string $value): string
    {
        $value = mb_strtolower($value);

        return in_array($value, ['wordpress', 'craft', 'other'], true) ? $value : '';
    }
}
