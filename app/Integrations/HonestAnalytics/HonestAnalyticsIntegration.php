<?php

declare(strict_types=1);

namespace App\Integrations\HonestAnalytics;

use App\Integrations\Connector\SignedConnectorClient;
use App\Integrations\Contracts\Collector;
use App\Integrations\Contracts\Integration;
use App\Integrations\Support\AuthMethod;
use App\Integrations\Support\ConfigField;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Support\IntegrationException;
use App\Integrations\Support\IntegrationManifest;
use App\Integrations\Support\VerificationResult;
use App\Models\SiteIntegration;

/**
 * Honest Analytics — the self-hosted, privacy-first WordPress analytics plugin.
 * Client Reporter pulls the site's aggregated stats over an HMAC-signed request
 * (the same connector-code scheme as the WordPress integration), so no visitor
 * data ever leaves the site's own domain unsigned.
 */
class HonestAnalyticsIntegration extends Integration
{
    /** The plugin serves its read API under its own REST namespace. */
    public const PATH_PREFIX = '/wp-json/honest-analytics/v1/';

    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'honest_analytics',
            name: 'Honest Analytics',
            category: IntegrationCategory::Analytics,
            authMethod: AuthMethod::ConnectorToken,
            description: 'Privacy-first, cookieless WordPress analytics: visitors, page views, top pages, sources and devices.',
            icon: 'vendor/logos/honest_analytics.svg',
            version: '1.0.0',
            connectorSlug: 'honest-analytics',
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
                label: 'WordPress site URL',
                type: 'url',
                required: true,
                secret: false,
                help: 'The public URL of the WordPress site running Honest Analytics.',
                placeholder: 'https://example.com',
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
            'In WordPress, make sure the <strong>Honest Analytics</strong> plugin is installed and active.',
            'Open <strong>Honest Analytics → Settings → Client Reporter</strong>.',
            'Copy the <strong>connection code</strong> shown below into that settings page and save.',
            'Come back here and press <strong>Connect &amp; verify</strong>.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        try {
            $data = $this->client($connection)->get('verify');
        } catch (IntegrationException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        if (($data['ok'] ?? false) !== true || ($data['connector'] ?? null) !== 'honest-analytics') {
            return VerificationResult::failure('The site responded, but not as an Honest Analytics connector. Check the plugin is active and the code is correct.');
        }

        return VerificationResult::success(
            'Connected to Honest Analytics.',
            ['connector_version' => (string) ($data['version'] ?? '')],
        );
    }

    /**
     * @return array<int, Collector>
     */
    public function collectors(): array
    {
        return [new HonestAnalyticsCollector];
    }

    /**
     * @return array<int, class-string>
     */
    public function reportBlocks(): array
    {
        // None of its own: it feeds the shared Analytics report blocks.
        return [];
    }

    public function client(SiteIntegration $connection): SignedConnectorClient
    {
        $baseUrl = (string) $connection->setting('base_url');
        $secret = (string) $connection->credential('secret');

        if ($baseUrl === '' || $secret === '') {
            throw new IntegrationException('This Honest Analytics connection is not fully configured yet.');
        }

        return new SignedConnectorClient($baseUrl, $secret, self::PATH_PREFIX);
    }
}
