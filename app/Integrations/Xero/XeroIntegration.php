<?php

declare(strict_types=1);

namespace App\Integrations\Xero;

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

/**
 * The agency's own Xero account — connected once for the workspace, with each
 * client mapped to a Xero contact. Never connected per site: billing is the
 * agency's own data, not something a client site produces.
 */
class XeroIntegration extends Integration
{
    public function manifest(): IntegrationManifest
    {
        return new IntegrationManifest(
            key: 'xero',
            name: 'Xero',
            category: IntegrationCategory::Billing,
            authMethod: AuthMethod::OAuth,
            description: "Sync invoices you've raised in Xero into your clients' reports automatically.",
            icon: 'vendor/logos/xero.svg',
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
        return route('integrations.workspace.xero.connect', $workspace);
    }

    /**
     * @return array<int, string>
     */
    public function workspaceSetupSteps(): array
    {
        return [
            'Click <strong>Connect Xero account</strong> and sign in, then authorise the organisation you invoice from.',
            'You’ll return here — click <strong>Find clients</strong> to list every contact in that organisation.',
            'Match each contact to a client below (already guessed by email or name where possible), then create the connections.',
            'From then on, invoices you raise against that contact in Xero appear automatically in that client’s reports.',
        ];
    }

    public function verify(SiteIntegration $connection): VerificationResult
    {
        return VerificationResult::failure('Xero is connected once for the whole workspace — see Integrations.');
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

        [$client, $accessToken, $tenantId] = $this->clientFor($workspace);

        return array_map(fn (array $contact): DiscoveredConnection => new DiscoveredConnection(
            externalId: (string) ($contact['ContactID'] ?? ''),
            label: (string) ($contact['Name'] ?? 'Contact'),
            url: null,
            settings: ['contact_id' => (string) ($contact['ContactID'] ?? '')],
            email: isset($contact['EmailAddress']) && $contact['EmailAddress'] !== '' ? (string) $contact['EmailAddress'] : null,
        ), $client->contacts($accessToken, $tenantId));
    }

    public function syncInvoices(ClientBillingConnection $link): int
    {
        [$client, $accessToken, $tenantId] = $this->clientFor($link->workspaceIntegration);

        $synced = 0;
        foreach ($client->invoicesForContact($accessToken, $tenantId, $link->external_contact_id) as $invoice) {
            $invoiceId = (string) ($invoice['InvoiceID'] ?? '');
            $issuedAt = XeroClient::parseDate($invoice['Date'] ?? null);
            if ($invoiceId === '' || $issuedAt === null) {
                continue;
            }

            Invoice::query()->updateOrCreate(
                ['client_id' => $link->client_id, 'source' => 'xero', 'external_id' => $invoiceId],
                [
                    'number' => (string) ($invoice['InvoiceNumber'] ?? $invoiceId),
                    'description' => null,
                    'amount' => (float) ($invoice['Total'] ?? 0),
                    'currency' => isset($invoice['CurrencyCode']) ? strtoupper((string) $invoice['CurrencyCode']) : null,
                    'status' => $this->mapStatus((string) ($invoice['Status'] ?? '')),
                    'issued_at' => $issuedAt->toDateString(),
                    'due_at' => XeroClient::parseDate($invoice['DueDate'] ?? null)?->toDateString(),
                    'paid_at' => XeroClient::parseDate($invoice['FullyPaidOnDate'] ?? null)?->toDateString(),
                ],
            );
            $synced++;
        }

        return $synced;
    }

    private function mapStatus(string $xeroStatus): InvoiceStatus
    {
        return match (strtoupper($xeroStatus)) {
            'PAID' => InvoiceStatus::Paid,
            'DRAFT', 'SUBMITTED' => InvoiceStatus::Draft,
            'VOIDED', 'DELETED' => InvoiceStatus::Void,
            default => InvoiceStatus::Sent, // AUTHORISED
        };
    }

    /**
     * Resolves the access token and tenant id for a workspace connection,
     * caching the tenant id in the connection's settings after the first
     * lookup so routine syncs don't repeat the /connections round trip.
     *
     * @return array{0: XeroClient, 1: string, 2: string}
     */
    private function clientFor(WorkspaceIntegration $workspace): array
    {
        $refreshToken = (string) $workspace->credential('refresh_token');
        if ($refreshToken === '') {
            throw new IntegrationException('This Xero connection is not fully configured yet.');
        }

        $client = new XeroClient(
            $refreshToken,
            (string) config('services.xero.client_id'),
            (string) config('services.xero.client_secret'),
        );
        $accessToken = $client->accessToken();

        $tenantId = (string) $workspace->setting('tenant_id', '');
        if ($tenantId === '') {
            $tenantId = (string) $client->firstTenantId($accessToken);
            if ($tenantId === '') {
                throw new IntegrationException('No Xero organisation is authorised for this connection yet.');
            }
            $workspace->update(['settings' => array_merge($workspace->settings ?? [], ['tenant_id' => $tenantId])]);
        }

        return [$client, $accessToken, $tenantId];
    }
}
