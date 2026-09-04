<?php

declare(strict_types=1);

namespace App\Integrations\WooCommerce;

use App\Integrations\Contracts\Collector;
use App\Integrations\Contracts\Integration;
use App\Integrations\Support\AuthMethod;
use App\Integrations\Support\ConfigField;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Support\IntegrationException;
use App\Integrations\Support\IntegrationManifest;
use App\Integrations\Support\VerificationResult;
use App\Models\SiteIntegration;

class WooCommerceIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'woocommerce',
            name: 'WooCommerce',
            category: IntegrationCategory::Ecommerce,
            authMethod: AuthMethod::ApiKey,
            description: 'Revenue, orders, average order value and best-selling products, straight from your WooCommerce store’s REST API — no plugin needed.',
            icon: 'vendor/logos/woocommerce.svg',
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
                key: 'store_url',
                label: 'Store URL',
                type: 'url',
                required: true,
                secret: false,
                help: 'The address customers visit, e.g. https://your-store.com.',
                placeholder: 'https://your-store.com',
            ),
            ConfigField::apiKey('consumer_key', 'Consumer key', 'From WooCommerce → Settings → Advanced → REST API (starts with ck_).'),
            ConfigField::apiKey('consumer_secret', 'Consumer secret', 'The matching secret (starts with cs_).'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function setupSteps(): array
    {
        return [
            'In your WordPress admin, go to <strong>WooCommerce → Settings → Advanced → REST API</strong>.',
            'Click <strong>Add key</strong>, give it a description (e.g. "Client Reporter") and set <strong>Permissions</strong> to <strong>Read</strong>.',
            'Click <strong>Generate API key</strong>, then copy the <strong>Consumer key</strong> and <strong>Consumer secret</strong> (shown only once).',
            'Paste them below with your store URL, then press <strong>Connect &amp; verify</strong>.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        try {
            $currency = (new WooCommerceRestClient(
                (string) $connection->setting('store_url'),
                (string) $connection->credential('consumer_key'),
                (string) $connection->credential('consumer_secret'),
            ))->currency();
        } catch (IntegrationException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        return VerificationResult::success(
            $currency !== null
                ? 'Connected. Store currency is '.$currency.'.'
                : 'Connected to WooCommerce.'
        );
    }

    /**
     * @return array<int, Collector>
     */
    public function collectors(): array
    {
        return [new SalesCollector];
    }

    /**
     * @return array<int, class-string>
     */
    public function reportBlocks(): array
    {
        // Sales render through the generic Store block, which reads any
        // ecommerce source, so there is no WooCommerce-specific block.
        return [];
    }
}
