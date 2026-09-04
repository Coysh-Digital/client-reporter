<?php

declare(strict_types=1);

namespace App\Integrations\Mailchimp;

use App\Integrations\Contracts\Collector;
use App\Integrations\Contracts\Integration;
use App\Integrations\Support\AuthMethod;
use App\Integrations\Support\ConfigField;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Support\IntegrationException;
use App\Integrations\Support\IntegrationManifest;
use App\Integrations\Support\VerificationResult;
use App\Models\SiteIntegration;

class MailchimpIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'mailchimp',
            name: 'Mailchimp',
            category: IntegrationCategory::Forms,
            authMethod: AuthMethod::ApiKey,
            description: 'Report new signups and audience growth from a Mailchimp list.',
            icon: 'vendor/logos/mailchimp.svg',
            version: '1.0.0',
        );
    }

    /**
     * @return array<int, ConfigField>
     */
    public function configFields(): array
    {
        return [
            ConfigField::apiKey(
                help: 'An API key from Mailchimp → Account → Extras → API keys.'
            ),
            new ConfigField(
                key: 'list_id',
                label: 'Audience ID',
                required: true,
                secret: false,
                help: 'Found in Mailchimp → Audience → Settings → Audience name and defaults.',
                placeholder: 'a1b2c3d4e5',
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
            'In Mailchimp, open <strong>Account</strong> → <strong>Extras</strong> → <strong>API keys</strong> and create a key.',
            'Open the audience you want to report on → <strong>Settings</strong> → <strong>Audience name and defaults</strong> and copy the Audience ID.',
            'Paste both below.',
            'Press <strong>Connect &amp; verify</strong>.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        try {
            $client = new MailchimpClient((string) $connection->credential('api_key'));
            $list = $client->list((string) $connection->setting('list_id'));
        } catch (IntegrationException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        $count = (int) ($list['stats']['member_count'] ?? 0);

        return VerificationResult::success("Connected to \"{$list['name']}\" ({$count} subscribers).");
    }

    /**
     * @return array<int, Collector>
     */
    public function collectors(): array
    {
        return [new SummaryCollector];
    }

    /**
     * @return array<int, class-string>
     */
    public function reportBlocks(): array
    {
        // The leads summary block is core + category-based, so any Forms &
        // Leads provider surfaces it; this integration registers no block of
        // its own.
        return [];
    }
}
