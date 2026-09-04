<?php

declare(strict_types=1);

namespace App\Integrations\Stripe;

use App\Integrations\Contracts\Collector;
use App\Integrations\Contracts\Integration;
use App\Integrations\Support\AuthMethod;
use App\Integrations\Support\ConfigField;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Support\IntegrationException;
use App\Integrations\Support\IntegrationManifest;
use App\Integrations\Support\VerificationResult;
use App\Models\SiteIntegration;
use App\Support\DateRange;
use Carbon\CarbonImmutable;

class StripeIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'stripe',
            name: 'Stripe',
            category: IntegrationCategory::Ecommerce,
            authMethod: AuthMethod::ApiKey,
            description: 'Revenue, payments, average payment value and refunds from your Stripe account.',
            icon: 'vendor/logos/stripe.svg',
            version: '1.0.0',
        );
    }

    /**
     * @return array<int, ConfigField>
     */
    public function configFields(): array
    {
        return [
            ConfigField::apiKey('api_key', 'Restricted API key', 'A restricted key with read access to Charges (starts with rk_).'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function setupSteps(): array
    {
        return [
            'In your Stripe Dashboard, go to <strong>Developers → API keys</strong>.',
            'Under <strong>Restricted keys</strong>, click <strong>Create restricted key</strong> and name it (e.g. "Client Reporter").',
            'Set <strong>Charges</strong> to <strong>Read</strong> and leave everything else as <strong>None</strong>, then create the key.',
            'Copy the key (it starts with <strong>rk_</strong>) and paste it below, then press <strong>Connect &amp; verify</strong>.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        try {
            // A tiny recent window keeps the check fast; success means the key
            // can read charges.
            (new StripeClient((string) $connection->credential('api_key')))
                ->charges(new DateRange(
                    CarbonImmutable::now()->subDay()->toDateString(),
                    CarbonImmutable::now()->toDateString(),
                ));
        } catch (IntegrationException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        return VerificationResult::success('Connected to Stripe.');
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
        // Payments render through the generic Store block, which reads any
        // ecommerce source, so there is no Stripe-specific block.
        return [];
    }
}
