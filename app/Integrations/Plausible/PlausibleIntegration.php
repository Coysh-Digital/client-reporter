<?php

declare(strict_types=1);

namespace App\Integrations\Plausible;

use App\Integrations\Contracts\Collector;
use App\Integrations\Contracts\Integration;
use App\Integrations\Support\AuthMethod;
use App\Integrations\Support\ConfigField;
use App\Integrations\Support\DiscoveredConnection;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Support\IntegrationException;
use App\Integrations\Support\IntegrationManifest;
use App\Integrations\Support\VerificationResult;
use App\Models\SiteIntegration;
use App\Models\WorkspaceIntegration;
use App\Support\DateRange;

class PlausibleIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'plausible',
            name: 'Plausible',
            category: IntegrationCategory::Analytics,
            authMethod: AuthMethod::ApiKey,
            description: 'Privacy-friendly visitor analytics: visitors, page views, top pages and sources.',
            icon: 'vendor/logos/plausible.svg',
            version: '1.0.0',
        );
    }

    /**
     * @return array<int, ConfigField>
     */
    public function configFields(): array
    {
        return [
            ConfigField::apiKey('api_token', 'API key', 'A Stats API key from Plausible → Settings → API keys.'),
            new ConfigField(key: 'site_id', label: 'Site ID (domain)', required: true, secret: false, help: 'e.g. example.com', placeholder: 'example.com', scope: 'site'),
            new ConfigField(key: 'base_url', label: 'Plausible URL', required: false, secret: false, help: 'Only for self-hosted Plausible. Leave blank for plausible.io.', placeholder: 'https://plausible.io'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function setupSteps(): array
    {
        return [
            'In Plausible, open <strong>Settings → API keys</strong> (top-right account menu).',
            'Click <strong>+ New API key</strong>, give it a name and copy the key.',
            'Enter the key and your site’s domain (its “Site ID”, e.g. example.com) below, then <strong>Connect &amp; verify</strong>.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        try {
            $client = new PlausibleClient(
                (string) $connection->credential('api_token'),
                (string) $connection->setting('site_id'),
                (string) ($connection->setting('base_url') ?: 'https://plausible.io'),
            );
            $client->aggregate(DateRange::last7Days(), ['visitors']);
        } catch (IntegrationException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        return VerificationResult::success('Connected to Plausible.');
    }

    public function supportsWorkspaceScope(): bool
    {
        return true;
    }

    /**
     * @return array<int, DiscoveredConnection>
     */
    public function discoverConnections(WorkspaceIntegration $workspace): array
    {
        $client = new PlausibleClient(
            (string) $workspace->credential('api_token'),
            '',
            (string) ($workspace->setting('base_url') ?: 'https://plausible.io'),
        );

        return array_map(function (array $site): DiscoveredConnection {
            $domain = (string) ($site['domain'] ?? '');

            return new DiscoveredConnection(
                externalId: $domain,
                label: $domain !== '' ? $domain : 'Site',
                url: $domain !== '' ? $domain : null,
                settings: ['site_id' => $domain],
            );
        }, $client->sites());
    }

    /**
     * @return array<int, Collector>
     */
    public function collectors(): array
    {
        return [new PlausibleCollector];
    }

    /**
     * @return array<int, class-string>
     */
    public function reportBlocks(): array
    {
        return [];
    }
}
