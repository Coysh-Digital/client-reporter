<?php

declare(strict_types=1);

namespace App\Integrations\WordPress;

use App\Integrations\Connector\SignedConnectorClient;
use App\Integrations\Contracts\Collector;
use App\Integrations\Contracts\Integration;
use App\Integrations\Support\AuthMethod;
use App\Integrations\Support\ConfigField;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Support\IntegrationException;
use App\Integrations\Support\IntegrationManifest;
use App\Integrations\Support\VerificationResult;
use App\Integrations\WordPress\Blocks\CmsStatusBlock;
use App\Integrations\WordPress\Blocks\CmsUpdatesBlock;
use App\Models\SiteIntegration;

class WordPressIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'wordpress',
            name: 'WordPress',
            category: IntegrationCategory::Cms,
            authMethod: AuthMethod::ConnectorToken,
            description: 'Report WordPress core, theme and plugin status, updates and (with WooCommerce) sales.',
            icon: 'vendor/logos/wordpress.svg',
            version: '1.0.0',
            connectorSlug: 'wordpress',
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
                help: 'The public URL of the WordPress site where you installed the Client Reporter plugin.',
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
            'In WordPress, go to <strong>Plugins → Add New</strong> and install the <strong>Client Reporter</strong> plugin, then activate it.',
            'Open <strong>Settings → Client Reporter</strong> in WordPress.',
            'Copy the <strong>connection code</strong> shown below and paste it into that settings page, then Save.',
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

        if (($data['ok'] ?? false) !== true || ($data['connector'] ?? null) !== 'wordpress') {
            return VerificationResult::failure('The site responded, but not as a WordPress Client Reporter connector.');
        }

        return VerificationResult::success(
            'Connected to WordPress '.($data['wordpress_version'] ?? '').'.',
            ['connector_version' => (string) ($data['version'] ?? '')],
        );
    }

    /**
     * @return array<int, Collector>
     */
    public function collectors(): array
    {
        return [new SiteStatusCollector, new WooCommerceCollector];
    }

    /**
     * @return array<int, class-string>
     */
    public function reportBlocks(): array
    {
        return [
            CmsStatusBlock::class,
            CmsUpdatesBlock::class,
        ];
    }

    public function client(SiteIntegration $connection): SignedConnectorClient
    {
        $baseUrl = (string) $connection->setting('base_url');
        $secret = (string) $connection->credential('secret');

        if ($baseUrl === '' || $secret === '') {
            throw new IntegrationException('This WordPress connection is not fully configured yet.');
        }

        return new SignedConnectorClient($baseUrl, $secret);
    }
}
