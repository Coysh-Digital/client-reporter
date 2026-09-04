<?php

declare(strict_types=1);

namespace App\Integrations\Shopify;

use App\Integrations\Contracts\Collector;
use App\Integrations\Contracts\Integration;
use App\Integrations\Support\AuthMethod;
use App\Integrations\Support\ConfigField;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Support\IntegrationException;
use App\Integrations\Support\IntegrationManifest;
use App\Integrations\Support\VerificationResult;
use App\Models\SiteIntegration;

class ShopifyIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'shopify',
            name: 'Shopify',
            category: IntegrationCategory::Ecommerce,
            authMethod: AuthMethod::ApiKey,
            description: 'Sales, orders, average order value and best-selling products from your Shopify store.',
            icon: 'vendor/logos/shopify.svg',
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
                key: 'shop_domain',
                label: 'Store domain',
                required: true,
                secret: false,
                help: 'Your myshopify.com domain — shown in your browser when you are in the Shopify admin.',
                placeholder: 'your-store.myshopify.com',
            ),
            ConfigField::apiKey('access_token', 'Admin API access token', 'The token from your custom app (starts with shpat_).'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function setupSteps(): array
    {
        return [
            'In your Shopify admin, go to <strong>Settings → Apps and sales channels → Develop apps</strong>.',
            'Click <strong>Create an app</strong>, give it a name (e.g. "Client Reporter"), then open <strong>Configuration → Admin API scopes</strong>.',
            'Tick <strong>read_orders</strong> and <strong>read_products</strong>, then <strong>Save</strong>.',
            'Open the <strong>API credentials</strong> tab, click <strong>Install app</strong>, then <strong>Reveal token once</strong> and copy the Admin API access token.',
            'Paste the token below with your <strong>your-store.myshopify.com</strong> domain, then press <strong>Connect &amp; verify</strong>.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        try {
            $shop = (new ShopifyClient(
                (string) $connection->setting('shop_domain'),
                (string) $connection->credential('access_token'),
            ))->shop();
        } catch (IntegrationException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        $name = isset($shop['name']) ? (string) $shop['name'] : '';

        return VerificationResult::success(
            $name !== ''
                ? 'Connected to '.$name.'.'
                : 'Connected to Shopify.'
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
        // Sales render through the generic Store block (App\Reporting\Blocks\
        // EcommerceBlock), which reads any ecommerce source, so no block here.
        return [];
    }
}
