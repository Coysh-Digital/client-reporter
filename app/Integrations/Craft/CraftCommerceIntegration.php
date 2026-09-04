<?php

declare(strict_types=1);

namespace App\Integrations\Craft;

use App\Integrations\Contracts\Collector;
use App\Integrations\Contracts\Integration;
use App\Integrations\Support\AuthMethod;
use App\Integrations\Support\ConfigField;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Support\IntegrationManifest;
use App\Integrations\Support\VerificationResult;
use App\Models\SiteIntegration;

/**
 * A catalogue presence for Craft Commerce. Craft Commerce has no standard
 * public API, so its sales are collected through the Craft CMS connector (the
 * companion plugin) — see {@see CraftCommerceCollector}, which runs under the
 * Craft integration. This class exists only so Craft Commerce appears in the
 * Ecommerce category as a capability of a connected Craft site; it is never
 * connected on its own (manifest()->providedBy = 'craft').
 */
class CraftCommerceIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'craft_commerce',
            name: 'Craft Commerce',
            category: IntegrationCategory::Ecommerce,
            authMethod: AuthMethod::ConnectorToken,
            description: 'Revenue, orders and best-selling products from Craft Commerce — reported automatically once your Craft CMS site is connected.',
            icon: 'vendor/logos/craft_commerce.svg',
            version: '1.0.0',
            providedBy: 'craft',
        );
    }

    /**
     * @return array<int, ConfigField>
     */
    public function configFields(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function setupSteps(): array
    {
        return [
            'Craft Commerce is read through the <strong>Craft CMS</strong> connection, so there is nothing to connect here separately.',
            'Connect the site’s <strong>Craft CMS</strong> integration (install the Client Reporter plugin and pair it).',
            'When that site runs Craft Commerce, its store metrics appear automatically — add a <strong>Store performance</strong> section to the report.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        return VerificationResult::failure('Connect this site’s Craft CMS integration — Craft Commerce is reported through it.');
    }

    /**
     * @return array<int, Collector>
     */
    public function collectors(): array
    {
        // None here: the store data is collected by the Craft integration's
        // CraftCommerceCollector, under the 'craft' connection.
        return [];
    }

    /**
     * @return array<int, class-string>
     */
    public function reportBlocks(): array
    {
        return [];
    }
}
