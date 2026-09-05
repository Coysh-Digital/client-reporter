<?php

declare(strict_types=1);

namespace App\Integrations\DownloadTracker;

use App\Integrations\Connector\SignedConnectorClient;
use App\Integrations\Contracts\Collector;
use App\Integrations\Contracts\Integration;
use App\Integrations\DownloadTracker\Blocks\DownloadsBlock;
use App\Integrations\Support\AuthMethod;
use App\Integrations\Support\ConfigField;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Support\IntegrationException;
use App\Integrations\Support\IntegrationManifest;
use App\Integrations\Support\VerificationResult;
use App\Models\SiteIntegration;

/**
 * Download Tracker — the Craft CMS plugin that counts file downloads. Client
 * Reporter pulls its aggregate download stats over an HMAC-signed request (the
 * same connector-code scheme as the other connectors) and reports them.
 */
class DownloadTrackerIntegration extends Integration
{
    /** The plugin serves its reporting API under this site-route prefix. */
    public const PATH_PREFIX = '/download-tracker/v1/';

    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'download_tracker',
            name: 'Download Tracker',
            category: IntegrationCategory::Downloads,
            authMethod: AuthMethod::ConnectorToken,
            description: 'File download counts from Download Tracker for Craft CMS: totals, top files and a daily trend.',
            icon: 'vendor/logos/download_tracker.svg',
            version: '1.0.0',
            connectorSlug: 'download-tracker',
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
                label: 'Craft site URL',
                type: 'url',
                required: true,
                secret: false,
                help: 'The public URL of the Craft site running Download Tracker.',
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
            'In Craft, make sure the <strong>Download Tracker</strong> plugin is installed and active.',
            'Open <strong>Download Tracker → Settings</strong> and find the <strong>Reporting API</strong> section.',
            'Copy the <strong>connection code</strong> shown below into that field and save.',
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

        if (($data['ok'] ?? false) !== true || ($data['connector'] ?? null) !== 'download-tracker') {
            return VerificationResult::failure('The site responded, but not as a Download Tracker connector. Check the plugin is active and the code is correct.');
        }

        return VerificationResult::success(
            'Connected to Download Tracker.',
            ['connector_version' => (string) ($data['version'] ?? '')],
        );
    }

    /**
     * @return array<int, Collector>
     */
    public function collectors(): array
    {
        return [new DownloadsCollector];
    }

    /**
     * @return array<int, class-string>
     */
    public function reportBlocks(): array
    {
        return [DownloadsBlock::class];
    }

    public function client(SiteIntegration $connection): SignedConnectorClient
    {
        $baseUrl = (string) $connection->setting('base_url');
        $secret = (string) $connection->credential('secret');

        if ($baseUrl === '' || $secret === '') {
            throw new IntegrationException('This Download Tracker connection is not fully configured yet.');
        }

        return new SignedConnectorClient($baseUrl, $secret, self::PATH_PREFIX);
    }
}
