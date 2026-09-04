<?php

declare(strict_types=1);

namespace App\Integrations\UptimeRobot;

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

class UptimeRobotIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'uptimerobot',
            name: 'UptimeRobot',
            category: IntegrationCategory::Monitoring,
            authMethod: AuthMethod::ApiKey,
            description: 'Report uptime, incidents and response times from your UptimeRobot monitors.',
            icon: 'vendor/logos/uptimerobot.svg',
            version: '1.0.0',
        );
    }

    /**
     * @return array<int, ConfigField>
     */
    public function configFields(): array
    {
        return [
            ConfigField::apiKey(
                help: 'A read-only or main API key from UptimeRobot → My Settings → API Settings.'
            ),
            new ConfigField(
                key: 'monitors',
                label: 'Monitor IDs',
                required: false,
                secret: false,
                help: 'Optional. Comma-separated monitor IDs to include. Leave blank to include all monitors on the account.',
                placeholder: '779035_781394',
                scope: 'site',
            ),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function setupSteps(): array
    {
        return [
            'In UptimeRobot, open <strong>My Settings</strong> (top-right menu).',
            'Under <strong>API Settings</strong>, create a <strong>Main API Key</strong> (or a Read-Only key) and copy it.',
            'Paste it below. Optionally list specific monitor IDs — leave blank to include the whole account.',
            'Press <strong>Connect &amp; verify</strong>.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        try {
            $client = new UptimeRobotClient((string) $connection->credential('api_key'));
            $monitors = $client->monitors();
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
     * Every monitor on the account, so each can be matched to a site by its URL.
     *
     * @return array<int, DiscoveredConnection>
     */
    public function discoverConnections(WorkspaceIntegration $workspace): array
    {
        $client = new UptimeRobotClient((string) $workspace->credential('api_key'));

        return array_map(function (array $monitor): DiscoveredConnection {
            $id = (string) ($monitor['id'] ?? '');

            return new DiscoveredConnection(
                externalId: $id,
                label: (string) ($monitor['friendly_name'] ?? $monitor['url'] ?? 'Monitor'),
                url: isset($monitor['url']) ? (string) $monitor['url'] : null,
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
        // Uptime blocks are core + category-based, so any monitoring provider
        // surfaces them; this integration no longer registers its own.
        return [];
    }
}
