<?php

declare(strict_types=1);

namespace App\Importers;

use App\Importers\Contracts\SiteImporter;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Imports sites from a WPMgr control plane (open-source WordPress fleet manager).
 * Uses the tenant site list at GET /api/v1/sites with an API key
 * (wpmgr_<prefix>_<secret>) sent as a bearer token. Defaults to the hosted
 * control plane; a self-hosted base URL can be supplied.
 */
class WpMgrImporter implements SiteImporter
{
    private const DEFAULT_BASE = 'https://manage.wpmgr.app';

    public function cmsType(): string
    {
        return 'wordpress';
    }

    public function key(): string
    {
        return 'wpmgr';
    }

    public function label(): string
    {
        return 'WPMgr';
    }

    public function description(): string
    {
        return 'Import the sites from your WPMgr control plane.';
    }

    public function configFields(): array
    {
        return [
            ['name' => 'api_key', 'label' => 'API key', 'type' => 'text', 'placeholder' => 'wpmgr_…', 'required' => true, 'help' => 'Create one in WPMgr under API keys. It carries your account\'s access.'],
            ['name' => 'base_url', 'label' => 'Control plane URL', 'type' => 'url', 'placeholder' => self::DEFAULT_BASE, 'required' => false, 'help' => 'Only needed for a self-hosted WPMgr. Leave blank for the hosted control plane.'],
        ];
    }

    public function fetchSites(array $config): array
    {
        $base = rtrim(($config['base_url'] ?? '') !== '' ? $config['base_url'] : self::DEFAULT_BASE, '/');
        $apiKey = trim($config['api_key'] ?? '');

        if ($apiKey === '') {
            throw new ImporterException('A WPMgr API key is required.');
        }

        try {
            // No query params: WPMgr's /sites endpoint doesn't recognise a
            // "state" filter (confirmed against a live instance — sending
            // state=active or include_archived at all silently zeroes the
            // result set rather than erroring). Filter client-side instead,
            // using the real "status" field the API actually returns.
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(20)
                ->get($base.'/api/v1/sites');
        } catch (Throwable $e) {
            throw new ImporterException('Could not reach WPMgr at '.$base.'.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new ImporterException('WPMgr rejected the API key. Check it has access to your sites.');
        }

        if (! $response->successful()) {
            throw new ImporterException('WPMgr returned an error ('.$response->status().').');
        }

        $sites = [];
        foreach ($response->json('items', []) as $item) {
            if (! is_array($item) || empty($item['url'])) {
                continue;
            }

            if (in_array($item['status'] ?? 'active', ['disabled', 'archived', 'suspended'], true)) {
                continue;
            }

            $sites[] = new ImportedSite(
                externalId: (string) ($item['id'] ?? $item['url']),
                name: (string) ($item['name'] ?? parse_url($item['url'], PHP_URL_HOST) ?? $item['url']),
                url: (string) $item['url'],
                suggestedClient: isset($item['client_name']) && $item['client_name'] !== '' ? (string) $item['client_name'] : null,
                meta: [
                    'wp_version' => $item['wp_version'] ?? null,
                    'health_status' => $item['health_status'] ?? null,
                    'updates_available' => $item['updates_available'] ?? null,
                ],
            );
        }

        return $sites;
    }
}
