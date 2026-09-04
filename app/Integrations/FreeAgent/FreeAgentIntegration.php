<?php

declare(strict_types=1);

namespace App\Integrations\FreeAgent;

use App\Enums\InvoiceStatus;
use App\Integrations\Contracts\Integration;
use App\Integrations\Support\AuthMethod;
use App\Integrations\Support\ConfigField;
use App\Integrations\Support\DiscoveredConnection;
use App\Integrations\Support\IntegrationCategory;
use App\Integrations\Support\IntegrationException;
use App\Integrations\Support\IntegrationManifest;
use App\Integrations\Support\VerificationResult;
use App\Models\ClientBillingConnection;
use App\Models\Invoice;
use App\Models\SiteIntegration;
use App\Models\WorkspaceIntegration;
use Carbon\CarbonImmutable;

/**
 * The agency's own FreeAgent account — connected once for the workspace, with
 * each client mapped to a FreeAgent contact. Never connected per site: billing
 * is the agency's own data, not something a client site produces.
 */
class FreeAgentIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'freeagent',
            name: 'FreeAgent',
            category: IntegrationCategory::Billing,
            authMethod: AuthMethod::OAuth,
            description: "Sync invoices you've raised in FreeAgent into your clients' reports automatically.",
            version: '1.0.0',
        );
    }

    /**
     * @return array<int, ConfigField>
     */
    public function configFields(): array
    {
        return [];
    }

    public function supportsWorkspaceScope(): bool
    {
        return true;
    }

    public function onlyWorkspaceScope(): bool
    {
        return true;
    }

    public function workspaceMapsTo(): string
    {
        return 'client';
    }

    public function oauthConnectUrl(WorkspaceIntegration $workspace): string
    {
        return route('integrations.workspace.freeagent.connect', $workspace);
    }

    /**
     * @return array<int, string>
     */
    public function workspaceSetupSteps(): array
    {
        return [
            'Click <strong>Connect FreeAgent account</strong> and sign in as the account that raises your invoices.',
            'You’ll return here — click <strong>Find clients</strong> to list every contact in your FreeAgent account.',
            'Match each contact to a client below (already guessed by email or name where possible), then create the connections.',
            'From then on, invoices you raise against that contact in FreeAgent appear automatically in that client’s reports.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        return VerificationResult::failure('FreeAgent is connected once for the whole workspace — see Integrations.');
    }

    /**
     * @return array<int, DiscoveredConnection>
     */
    public function discoverConnections(WorkspaceIntegration $workspace): array
    {
        $refreshToken = (string) $workspace->credential('refresh_token');
        if ($refreshToken === '') {
            return [];
        }

        $client = $this->clientFor($workspace);

        return array_map(function (array $contact): DiscoveredConnection {
            $name = (string) ($contact['organisation_name'] ?? trim(($contact['first_name'] ?? '').' '.($contact['last_name'] ?? '')));

            return new DiscoveredConnection(
                externalId: (string) ($contact['url'] ?? ''),
                label: $name !== '' ? $name : 'Contact',
                url: null,
                settings: ['contact_url' => (string) ($contact['url'] ?? '')],
                email: isset($contact['email']) ? (string) $contact['email'] : null,
            );
        }, $client->contacts());
    }

    public function syncInvoices(ClientBillingConnection $link): int
    {
        $workspace = $link->workspaceIntegration;
        $client = $this->clientFor($workspace);

        $synced = 0;
        foreach ($client->invoicesForContact($link->external_contact_id) as $invoice) {
            $reference = (string) ($invoice['url'] ?? $invoice['reference'] ?? '');
            if ($reference === '') {
                continue;
            }

            $datedOn = isset($invoice['dated_on']) ? CarbonImmutable::parse((string) $invoice['dated_on']) : null;
            if ($datedOn === null) {
                continue;
            }

            Invoice::query()->updateOrCreate(
                ['client_id' => $link->client_id, 'source' => 'freeagent', 'external_id' => $reference],
                [
                    'number' => (string) ($invoice['reference'] ?? $reference),
                    'description' => null,
                    'amount' => (float) ($invoice['total_value'] ?? 0),
                    'currency' => isset($invoice['currency']) ? strtoupper((string) $invoice['currency']) : null,
                    'status' => $this->mapStatus((string) ($invoice['status'] ?? '')),
                    'issued_at' => $datedOn->toDateString(),
                    'due_at' => isset($invoice['due_on']) ? CarbonImmutable::parse((string) $invoice['due_on'])->toDateString() : null,
                    'paid_at' => isset($invoice['paid_on']) ? CarbonImmutable::parse((string) $invoice['paid_on'])->toDateString() : null,
                ],
            );
            $synced++;
        }

        return $synced;
    }

    /**
     * FreeAgent's status vocabulary is richer than ours (Draft, Scheduled,
     * Sent, Viewed, Paid, Partially Paid, Overdue, Void, Written-off, Refunded,
     * …) — normalise it onto our simpler set. "Overdue" is not mapped
     * specially: a Sent invoice past its due date is already derived as
     * overdue by {@see Invoice::isOverdue()}, regardless of source.
     */
    private function mapStatus(string $freeAgentStatus): InvoiceStatus
    {
        $status = strtolower($freeAgentStatus);

        return match (true) {
            str_contains($status, 'paid') => InvoiceStatus::Paid,
            $status === 'draft' => InvoiceStatus::Draft,
            str_contains($status, 'void'), str_contains($status, 'written'), str_contains($status, 'cancel') => InvoiceStatus::Void,
            default => InvoiceStatus::Sent,
        };
    }

    private function clientFor(WorkspaceIntegration $workspace): FreeAgentClient
    {
        $refreshToken = (string) $workspace->credential('refresh_token');
        if ($refreshToken === '') {
            throw new IntegrationException('This FreeAgent connection is not fully configured yet.');
        }

        return new FreeAgentClient(
            $refreshToken,
            (string) config('services.freeagent.client_id'),
            (string) config('services.freeagent.client_secret'),
        );
    }
}
