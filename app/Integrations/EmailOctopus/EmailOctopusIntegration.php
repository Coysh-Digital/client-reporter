<?php

declare(strict_types=1);

namespace App\Integrations\EmailOctopus;

use App\Integrations\Contracts\Collector;
use App\Integrations\Contracts\Integration;
use App\Integrations\Support\AuthMethod;
use App\Integrations\Support\ConfigField;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Support\IntegrationException;
use App\Integrations\Support\IntegrationManifest;
use App\Integrations\Support\VerificationResult;
use App\Models\SiteIntegration;

class EmailOctopusIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'email_octopus',
            name: 'EmailOctopus',
            category: IntegrationCategory::Forms,
            authMethod: AuthMethod::ApiKey,
            description: 'Report new subscribers and audience growth from an EmailOctopus list.',
            icon: 'vendor/logos/emailoctopus.svg',
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
                help: 'From EmailOctopus → Account → Integrations & API → API keys. Generate a new (non-legacy) key if yours predates API v2.'
            ),
            new ConfigField(
                key: 'list_id',
                label: 'List ID',
                required: true,
                secret: false,
                help: 'Found under Contacts: open the list you want to report on and copy the ID from the page\'s web address — a UUID like 00000000-0000-0000-0000-000000000000.',
                placeholder: '00000000-0000-0000-0000-000000000000',
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
            'In EmailOctopus, open <strong>Account → Integrations &amp; API</strong>, then <strong>API keys</strong>, and create a key (generate a new one if yours is labelled "legacy").',
            'Under <strong>Contacts</strong>, open the list you want to report on and copy its <strong>ID</strong> from the page\'s web address.',
            'Paste both below.',
            'Press <strong>Connect &amp; verify</strong>.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        try {
            $client = new EmailOctopusClient((string) $connection->credential('api_key'));
            $list = $client->list((string) $connection->setting('list_id'));
        } catch (IntegrationException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        $count = EmailOctopusClient::countsFor($list)['subscribed'];
        $name = (string) ($list['name'] ?? 'list');

        return VerificationResult::success("Connected to \"{$name}\" ({$count} subscribers).");
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
