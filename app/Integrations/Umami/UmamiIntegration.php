<?php

declare(strict_types=1);

namespace App\Integrations\Umami;

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

class UmamiIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'umami',
            name: 'Umami',
            category: IntegrationCategory::Analytics,
            authMethod: AuthMethod::ApiKey,
            description: 'Simple, privacy-first analytics: visitors, page views, top pages and referrers.',
            icon: 'vendor/logos/umami.svg',
            version: '1.0.0',
        );
    }

    /**
     * @return array<int, ConfigField>
     */
    public function configFields(): array
    {
        return [
            ConfigField::apiKey('api_key', 'API key', 'A key from Umami Cloud → Settings → API, or your self-hosted instance.'),
            new ConfigField(key: 'website_id', label: 'Website ID', required: true, secret: false, help: 'The website UUID from its Umami settings.', placeholder: '00000000-0000-0000-0000-000000000000', scope: 'site'),
            new ConfigField(key: 'base_url', label: 'API base URL', required: false, secret: false, help: 'Leave blank for Umami Cloud. Self-hosted: https://your-umami/api', placeholder: 'https://api.umami.is/v1'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function setupSteps(): array
    {
        return [
            'On <strong>Umami Cloud</strong>: open <strong>Settings → API keys</strong> and create a key. (Self-hosted: create an API key and set the base URL below.)',
            'Open your website in Umami and copy its <strong>Website ID</strong> from <strong>Settings → Websites</strong>.',
            'Paste the API key and website ID below, then <strong>Connect &amp; verify</strong>.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        try {
            (new UmamiClient(
                (string) ($connection->setting('base_url') ?: 'https://api.umami.is/v1'),
                (string) $connection->credential('api_key'),
                (string) $connection->setting('website_id'),
            ))->stats(DateRange::last7Days());
        } catch (IntegrationException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        return VerificationResult::success('Connected to Umami.');
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
        $client = new UmamiClient(
            (string) ($workspace->setting('base_url') ?: 'https://api.umami.is/v1'),
            (string) $workspace->credential('api_key'),
            '',
        );

        return array_map(function (array $site): DiscoveredConnection {
            $domain = isset($site['domain']) ? (string) $site['domain'] : null;

            return new DiscoveredConnection(
                externalId: (string) ($site['id'] ?? ''),
                label: (string) ($site['name'] ?? $domain ?? 'Site'),
                url: $domain,
                settings: ['website_id' => (string) ($site['id'] ?? '')],
            );
        }, $client->websites());
    }

    /**
     * @return array<int, Collector>
     */
    public function collectors(): array
    {
        return [new UmamiCollector];
    }

    /**
     * @return array<int, class-string>
     */
    public function reportBlocks(): array
    {
        return [];
    }
}
