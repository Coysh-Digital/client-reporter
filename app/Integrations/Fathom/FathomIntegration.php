<?php

declare(strict_types=1);

namespace App\Integrations\Fathom;

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

class FathomIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'fathom',
            name: 'Fathom',
            category: IntegrationCategory::Analytics,
            authMethod: AuthMethod::ApiKey,
            description: 'Simple, privacy-first analytics: visitors, page views, top pages and referrers.',
            icon: 'vendor/logos/fathom.svg',
            version: '1.0.0',
        );
    }

    /**
     * @return array<int, ConfigField>
     */
    public function configFields(): array
    {
        return [
            ConfigField::apiKey('api_token', 'API token', 'An API token from Fathom → Settings → API.'),
            new ConfigField(key: 'site_id', label: 'Site ID', required: true, secret: false, help: 'The Fathom site ID (e.g. ABCDEFG).', placeholder: 'ABCDEFG', scope: 'site'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function setupSteps(): array
    {
        return [
            'In Fathom, click your email (top-right) then <strong>Settings → API</strong>.',
            'Create an <strong>API token</strong> and copy it.',
            'Find your <strong>Site ID</strong> under <strong>Settings → Sites</strong> (a short code like ABCDEFG).',
            'Paste the token and Site ID below, then <strong>Connect &amp; verify</strong>.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        try {
            $client = new FathomClient(
                (string) $connection->credential('api_token'),
                (string) $connection->setting('site_id'),
            );
            $client->aggregations(DateRange::last7Days(), ['uniques']);
        } catch (IntegrationException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        return VerificationResult::success('Connected to Fathom.');
    }

    public function supportsWorkspaceScope(): bool
    {
        return true;
    }

    /**
     * Fathom's site listing has no domain field, only the label the account
     * gave it when creating the site — which is conventionally the site's
     * domain, so it still feeds URL auto-matching reasonably well.
     *
     * @return array<int, DiscoveredConnection>
     */
    public function discoverConnections(WorkspaceIntegration $workspace): array
    {
        $client = new FathomClient((string) $workspace->credential('api_token'), '');

        return array_map(function (array $site): DiscoveredConnection {
            $id = (string) ($site['id'] ?? '');
            $name = isset($site['name']) ? (string) $site['name'] : null;

            return new DiscoveredConnection(
                externalId: $id,
                label: $name ?? ($id !== '' ? $id : 'Site'),
                url: $name,
                settings: ['site_id' => $id],
            );
        }, $client->sites());
    }

    /**
     * @return array<int, Collector>
     */
    public function collectors(): array
    {
        return [new FathomCollector];
    }

    /**
     * @return array<int, class-string>
     */
    public function reportBlocks(): array
    {
        return [];
    }
}
