<?php

declare(strict_types=1);

namespace App\Integrations\Craft;

use App\Integrations\Connector\SignedConnectorClient;
use App\Integrations\Contracts\Collector;
use App\Integrations\Contracts\Integration;
use App\Integrations\Craft\Blocks\CraftStatusBlock;
use App\Integrations\Craft\Blocks\CraftUpdatesBlock;
use App\Integrations\Support\AuthMethod;
use App\Integrations\Support\ConfigField;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Support\IntegrationException;
use App\Integrations\Support\IntegrationManifest;
use App\Integrations\Support\VerificationResult;
use App\Models\SiteIntegration;

class CraftIntegration extends Integration
{
    public const PATH_PREFIX = '/client-reporter/v1/';

    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'craft',
            name: 'Craft CMS',
            category: IntegrationCategory::Cms,
            authMethod: AuthMethod::ConnectorToken,
            description: 'Report Craft CMS version, plugins, updates, queue health and (with Craft Commerce) sales.',
            icon: 'vendor/logos/craft.svg',
            version: '1.0.0',
            connectorSlug: 'craft',
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
                help: 'The public URL of the Craft site where you installed the Client Reporter plugin.',
                placeholder: 'https://example.com',
            ),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function setupSteps(): array
    {
        return [
            'Install the <strong>Client Reporter</strong> plugin on your Craft site (via the Plugin Store or Composer), then enable it.',
            'In the Craft control panel, open the <strong>Client Reporter</strong> settings.',
            'Copy the <strong>connection code</strong> below and paste it into those settings, then Save.',
            'Return here and press <strong>Connect &amp; verify</strong>.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        try {
            $data = $this->client($connection)->get('verify');
        } catch (IntegrationException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        if (($data['ok'] ?? false) !== true || ($data['connector'] ?? null) !== 'craft') {
            return VerificationResult::failure('The site responded, but not as a Craft Client Reporter connector.');
        }

        return VerificationResult::success(
            'Connected to Craft '.($data['craft_version'] ?? '').'.',
            ['connector_version' => (string) ($data['version'] ?? '')],
        );
    }

    /**
     * @return array<int, Collector>
     */
    public function collectors(): array
    {
        return [new CraftStatusCollector, new CraftCommerceCollector];
    }

    /**
     * @return array<int, class-string>
     */
    public function reportBlocks(): array
    {
        return [
            CraftStatusBlock::class,
            CraftUpdatesBlock::class,
        ];
    }

    public function client(SiteIntegration $connection): SignedConnectorClient
    {
        $baseUrl = (string) $connection->setting('base_url');
        $secret = (string) $connection->credential('secret');

        if ($baseUrl === '' || $secret === '') {
            throw new IntegrationException('This Craft connection is not fully configured yet.');
        }

        return new SignedConnectorClient($baseUrl, $secret, self::PATH_PREFIX);
    }
}
