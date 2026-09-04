<?php

declare(strict_types=1);

namespace App\Integrations\Matomo;

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

class MatomoIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'matomo',
            name: 'Matomo',
            category: IntegrationCategory::Analytics,
            authMethod: AuthMethod::ApiKey,
            description: 'Open-source, privacy-friendly analytics: visitors, page views, top pages and sources.',
            icon: 'vendor/logos/matomo.svg',
            version: '1.0.0',
        );
    }

    /**
     * @return array<int, ConfigField>
     */
    public function configFields(): array
    {
        return [
            new ConfigField(key: 'base_url', label: 'Matomo URL', required: true, secret: false, help: 'Your Matomo address, e.g. https://analytics.example.com', placeholder: 'https://analytics.example.com'),
            ConfigField::apiKey('token', 'Auth token', 'A token from Matomo → Administration → Personal → Security → Auth tokens.'),
            new ConfigField(key: 'site_id', label: 'Site ID (idSite)', required: true, secret: false, help: 'The numeric site ID from Matomo → Websites.', placeholder: '1', scope: 'site'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function setupSteps(): array
    {
        return [
            'In Matomo, click the cog (Administration), then <strong>Personal → Security</strong>.',
            'Under <strong>Auth tokens</strong>, click <strong>Create new token</strong> and copy it.',
            'Find your site’s numeric ID under <strong>Websites → Manage</strong> (the “ID” column).',
            'Paste your Matomo URL, the token and the site ID below, then <strong>Connect &amp; verify</strong>.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        try {
            (new MatomoClient(
                (string) $connection->setting('base_url'),
                (string) $connection->credential('token'),
                (string) $connection->setting('site_id'),
            ))->visitsSummary(DateRange::last7Days());
        } catch (IntegrationException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        return VerificationResult::success('Connected to Matomo.');
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
        $client = new MatomoClient(
            (string) $workspace->setting('base_url'),
            (string) $workspace->credential('token'),
            '',
        );

        return array_map(function (array $site): DiscoveredConnection {
            $id = (string) ($site['idsite'] ?? '');

            return new DiscoveredConnection(
                externalId: $id,
                label: (string) ($site['name'] ?? 'Site'),
                url: isset($site['main_url']) ? (string) $site['main_url'] : null,
                settings: ['site_id' => $id],
            );
        }, $client->allSites());
    }

    /**
     * @return array<int, Collector>
     */
    public function collectors(): array
    {
        return [new MatomoCollector];
    }

    /**
     * @return array<int, class-string>
     */
    public function reportBlocks(): array
    {
        return [];
    }
}
