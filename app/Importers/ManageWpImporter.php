<?php

declare(strict_types=1);

namespace App\Importers;

use App\Importers\Contracts\SiteImporter;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Imports websites from a ManageWP account via its API, authenticated with an
 * API key sent as a bearer token. The base URL is configurable so the connector
 * can be pointed at the correct ManageWP API host for the account.
 */
class ManageWpImporter implements SiteImporter
{
    private const DEFAULT_BASE = 'https://api.managewp.com';

    public function cmsType(): string
    {
        return 'wordpress';
    }

    public function key(): string
    {
        return 'managewp';
    }

    public function label(): string
    {
        return 'ManageWP';
    }

    public function description(): string
    {
        return 'Import the websites from your ManageWP account.';
    }

    public function configFields(): array
    {
        return [
            ['name' => 'api_key', 'label' => 'API key', 'type' => 'text', 'placeholder' => 'Your ManageWP API key', 'required' => true, 'help' => 'ManageWP → Account settings → API.'],
            ['name' => 'base_url', 'label' => 'API base URL', 'type' => 'url', 'placeholder' => self::DEFAULT_BASE, 'required' => false, 'help' => 'Only change this if ManageWP gave you a different API host.'],
        ];
    }

    public function fetchSites(array $config): array
    {
        $base = rtrim(($config['base_url'] ?? '') !== '' ? $config['base_url'] : self::DEFAULT_BASE, '/');
        $apiKey = trim($config['api_key'] ?? '');

        if ($apiKey === '') {
            throw new ImporterException('A ManageWP API key is required.');
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(20)
                ->get($base.'/websites');
        } catch (Throwable $e) {
            throw new ImporterException('Could not reach ManageWP at '.$base.'.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new ImporterException('ManageWP rejected the API key.');
        }

        if (! $response->successful()) {
            throw new ImporterException('ManageWP returned an error ('.$response->status().').');
        }

        // Accept a few common envelope shapes.
        $data = $response->json('websites', $response->json('data', $response->json()));
        if (! is_array($data)) {
            throw new ImporterException('ManageWP returned an unexpected response.');
        }
        $rows = array_is_list($data) ? $data : array_values($data);

        $sites = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $url = $row['url'] ?? $row['site_url'] ?? null;
            if (! $url) {
                continue;
            }

            $sites[] = new ImportedSite(
                externalId: (string) ($row['id'] ?? $url),
                name: (string) ($row['name'] ?? $row['title'] ?? parse_url($url, PHP_URL_HOST) ?? $url),
                url: (string) $url,
            );
        }

        return $sites;
    }
}
