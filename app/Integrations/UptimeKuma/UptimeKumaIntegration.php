<?php

declare(strict_types=1);

namespace App\Integrations\UptimeKuma;

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

class UptimeKumaIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'uptime_kuma',
            name: 'Uptime Kuma',
            category: IntegrationCategory::Monitoring,
            authMethod: AuthMethod::ApiKey,
            description: 'Report uptime, incidents and response times from your self-hosted Uptime Kuma instance.',
            version: '1.0.0',
        );
    }

    /**
     * @return array<int, ConfigField>
     */
    public function configFields(): array
    {
        return [
            new ConfigField(
                key: 'base_url',
                label: 'Uptime Kuma URL',
                type: 'url',
                required: true,
                secret: false,
                help: 'The base URL of your Uptime Kuma instance, publicly reachable so it can be polled.',
                placeholder: 'https://status.example.com',
            ),
            ConfigField::apiKey(
                help: 'An API key from Uptime Kuma → Settings → API Keys.'
            ),
            new ConfigField(
                key: 'monitors',
                label: 'Monitor names',
                required: false,
                secret: false,
                help: 'Optional. Comma-separated monitor names, exactly as shown in Uptime Kuma, to include. Leave blank to include every monitor the key can see.',
                placeholder: 'Website, API',
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
            'In Uptime Kuma, open <strong>Settings</strong> → <strong>API Keys</strong> and create a new key.',
            'Copy your Uptime Kuma instance\'s public URL and paste it below with the key.',
            'Optionally list specific monitor names to include — leave blank to include every monitor the key can see.',
            'Press <strong>Connect &amp; verify</strong>.',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function workspaceSetupSteps(): array
    {
        return [
            'In Uptime Kuma, open <strong>Settings</strong> → <strong>API Keys</strong> and create a new key.',
            'Copy your Uptime Kuma instance\'s public URL and paste it below with the key — this covers every monitor on the instance.',
            'Click <strong>Find sites</strong> to list every monitor, then map each one to a site below.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        try {
            $monitors = self::clientFor($connection)->monitors();
        } catch (IntegrationException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        return VerificationResult::success(
            count($monitors) > 0
                ? 'Connected. Found '.count($monitors).' monitor(s).'
                : 'Connected, but no monitors were found on this instance yet.'
        );
    }

    public function supportsWorkspaceScope(): bool
    {
        return true;
    }

    /**
     * Every monitor on the instance, so each can be matched to a site by URL.
     *
     * @return array<int, DiscoveredConnection>
     */
    public function discoverConnections(WorkspaceIntegration $workspace): array
    {
        $client = new UptimeKumaClient(
            (string) $workspace->setting('base_url'),
            (string) $workspace->credential('api_key'),
        );

        return array_map(fn (array $monitor): DiscoveredConnection => new DiscoveredConnection(
            externalId: $monitor['name'],
            label: $monitor['name'],
            url: $monitor['url'],
            settings: ['monitors' => $monitor['name']],
        ), $client->monitors());
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
        // surfaces them; this integration registers no blocks of its own.
        return [];
    }

    /**
     * Resolves the client for a per-site connection. `base_url` is a
     * non-secret field (stored in `settings`), which — unlike `credentials`
     * — has no built-in workspace fallback on SiteIntegration::setting(), so
     * a workspace-mapped connection (whose own settings only carry the
     * matched `monitors` name) would otherwise never find it. Fall back to
     * the linked workspace's setting explicitly here instead of changing
     * that shared model method for every integration.
     */
    public static function clientFor(SiteIntegration $connection): UptimeKumaClient
    {
        $baseUrl = (string) $connection->setting('base_url');
        if ($baseUrl === '' && $connection->usesWorkspace()) {
            $baseUrl = (string) $connection->workspaceIntegration?->setting('base_url');
        }

        $apiKey = (string) $connection->credential('api_key');

        if ($baseUrl === '' || $apiKey === '') {
            throw new IntegrationException('This Uptime Kuma connection is not fully configured yet.');
        }

        return new UptimeKumaClient($baseUrl, $apiKey);
    }
}
