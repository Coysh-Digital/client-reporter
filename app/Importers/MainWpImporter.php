<?php

declare(strict_types=1);

namespace App\Importers;

use App\Importers\Contracts\SiteImporter;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Imports sites from a self-hosted MainWP dashboard via its REST API
 * (GET /wp-json/mainwp/v1/sites/all-sites), authenticated with the consumer
 * key/secret pair generated in MainWP → REST API.
 */
class MainWpImporter implements SiteImporter
{
    public function cmsType(): string
    {
        return 'wordpress';
    }

    public function key(): string
    {
        return 'mainwp';
    }

    public function label(): string
    {
        return 'MainWP';
    }

    public function description(): string
    {
        return 'Import the child sites from your self-hosted MainWP dashboard.';
    }

    public function configFields(): array
    {
        return [
            ['name' => 'dashboard_url', 'label' => 'Dashboard URL', 'type' => 'url', 'placeholder' => 'https://dashboard.example.com', 'required' => true, 'help' => 'The WordPress site your MainWP dashboard runs on.'],
            ['name' => 'consumer_key', 'label' => 'Consumer key', 'type' => 'text', 'placeholder' => 'ck_…', 'required' => true, 'help' => 'MainWP → REST API → Add key.'],
            ['name' => 'consumer_secret', 'label' => 'Consumer secret', 'type' => 'text', 'placeholder' => 'cs_…', 'required' => true],
        ];
    }

    public function fetchSites(array $config): array
    {
        $base = rtrim(trim($config['dashboard_url'] ?? ''), '/');
        $ck = trim($config['consumer_key'] ?? '');
        $cs = trim($config['consumer_secret'] ?? '');

        if ($base === '' || $ck === '' || $cs === '') {
            throw new ImporterException('The MainWP dashboard URL, consumer key and consumer secret are all required.');
        }

        try {
            $response = Http::acceptJson()
                ->timeout(20)
                ->get($base.'/wp-json/mainwp/v1/sites/all-sites', [
                    'consumer_key' => $ck,
                    'consumer_secret' => $cs,
                ]);
        } catch (Throwable $e) {
            throw new ImporterException('Could not reach the MainWP dashboard at '.$base.'.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new ImporterException('MainWP rejected the consumer key/secret.');
        }

        if (! $response->successful()) {
            throw new ImporterException('MainWP returned an error ('.$response->status().').');
        }

        // MainWP returns either a list or an object keyed by site id.
        $data = $response->json();
        if (! is_array($data)) {
            throw new ImporterException('MainWP returned an unexpected response.');
        }
        $rows = array_is_list($data) ? $data : array_values($data);

        $sites = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $url = $row['url'] ?? $row['siteurl'] ?? null;
            if (! $url) {
                continue;
            }

            $sites[] = new ImportedSite(
                externalId: (string) ($row['id'] ?? $url),
                name: (string) ($row['name'] ?? parse_url($url, PHP_URL_HOST) ?? $url),
                url: (string) $url,
                meta: [
                    'wp_version' => $row['wpversion'] ?? $row['wp_version'] ?? null,
                ],
            );
        }

        return $sites;
    }
}
