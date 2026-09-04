<?php

declare(strict_types=1);

namespace App\Integrations\BetterUptime;

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

class BetterUptimeIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'better_uptime',
            name: 'Better Uptime',
            category: IntegrationCategory::Monitoring,
            authMethod: AuthMethod::ApiKey,
            description: 'Uptime, incidents and downtime from your Better Stack (Better Uptime) monitors.',
            icon: 'vendor/logos/better_uptime.svg',
            version: '1.0.0',
        );
    }

    /**
     * @return array<int, ConfigField>
     */
    public function configFields(): array
    {
        return [
            ConfigField::apiKey('api_key', 'API token', 'A token from Better Stack → Settings → API tokens.'),
            new ConfigField(key: 'monitors', label: 'Monitor IDs', required: false, secret: false, help: 'Optional. Comma-separated monitor IDs to include. Leave blank for all monitors.', placeholder: '12345, 67890', scope: 'site'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function setupSteps(): array
    {
        return [
            'In Better Stack, open <strong>Settings → API tokens</strong>.',
            'Click <strong>Create API token</strong> and copy it.',
            'Paste it below. Optionally list specific monitor IDs — leave blank for the whole account.',
            'Press <strong>Connect &amp; verify</strong>.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        try {
            $monitors = (new BetterUptimeClient(
                (string) $connection->credential('api_key'),
                (string) ($connection->setting('base_url') ?: 'https://uptime.betterstack.com'),
            ))->monitors();
        } catch (IntegrationException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        return VerificationResult::success(
            count($monitors) > 0
                ? 'Connected. Found '.count($monitors).' monitor(s).'
                : 'Connected, but no monitors were found on this account yet.'
        );
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
        $client = new BetterUptimeClient(
            (string) $workspace->credential('api_key'),
            (string) ($workspace->setting('base_url') ?: 'https://uptime.betterstack.com'),
        );

        return array_map(function (array $monitor): DiscoveredConnection {
            $id = (string) ($monitor['id'] ?? '');
            $attributes = (array) ($monitor['attributes'] ?? []);

            return new DiscoveredConnection(
                externalId: $id,
                label: (string) ($attributes['pronounceable_name'] ?? $attributes['url'] ?? 'Monitor'),
                url: isset($attributes['url']) ? (string) $attributes['url'] : null,
                settings: ['monitors' => $id],
            );
        }, $client->monitors());
    }

    /**
     * @return array<int, Collector>
     */
    public function collectors(): array
    {
        return [new MonitorsCollector];
    }

    /**
     * @return array<int, class-string>
     */
    public function reportBlocks(): array
    {
        return [];
    }
}
