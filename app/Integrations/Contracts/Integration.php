<?php

declare(strict_types=1);

namespace App\Integrations\Contracts;

use App\Integrations\Support\ConfigField;
use App\Integrations\Support\DiscoveredConnection;
use App\Integrations\Support\IntegrationManifest;
use App\Integrations\Support\VerificationResult;
use App\Models\ClientBillingConnection;
use App\Models\Invoice;
use App\Models\SiteIntegration;
use App\Models\WorkspaceIntegration;
use LogicException;

/**
 * The contract every integration implements — first-party and third-party
 * alike. An integration describes itself (manifest), declares the fields its
 * connection form needs (configFields), verifies a connection's credentials
 * (verify), and provides the collectors that gather data and the report blocks
 * that present it.
 *
 * Third-party integrations ship as Composer packages that register their
 * Integration class via `extra.client-reporter.integrations`; they need not
 * modify Client Reporter's core.
 */
abstract class Integration
{
    abstract public function manifest(): IntegrationManifest;

    /**
     * Fields shown on the connection/setup form.
     *
     * @return array<int, ConfigField>
     */
    abstract public function configFields(): array;

    /**
     * Ordered, plain-language steps for connecting this integration, shown as a
     * numbered "How to connect" guide on the setup screen. Empty by default.
     *
     * @return array<int, string>
     */
    public function setupSteps(): array
    {
        return [];
    }

    /**
     * Test a connection's stored credentials against the external service.
     */
    abstract public function verify(SiteIntegration $connection): VerificationResult;

    /**
     * Ordered steps for the workspace ("connect once") flow. Defaults to
     * {@see setupSteps()}; override when the global flow differs meaningfully
     * (e.g. no per-site field to paste, since that gets decided during mapping).
     *
     * @return array<int, string>
     */
    public function workspaceSetupSteps(): array
    {
        return $this->setupSteps();
    }

    /**
     * Whether this integration can be connected once for the whole workspace
     * (one API key/token covering every site or client), with the provider's
     * entities auto-matched by {@see workspaceMapsTo()}. Providers that opt in
     * must implement {@see discoverConnections()}. Inherently per-site
     * integrations (a CMS, a single store) leave this false.
     */
    public function supportsWorkspaceScope(): bool
    {
        return false;
    }

    /**
     * What a workspace connection's discovered entities map onto: 'site' (the
     * default — monitors, analytics properties) or 'client' (billing/accounting
     * contacts, matched to a Client rather than a Site).
     */
    public function workspaceMapsTo(): string
    {
        return 'site';
    }

    /**
     * Whether this integration can ONLY be connected at the workspace level —
     * there is no meaningful per-site connection at all (e.g. an accounting
     * system billing the agency's clients). Such integrations skip the
     * per-site "connect on a site" flow entirely in the catalog.
     */
    public function onlyWorkspaceScope(): bool
    {
        return false;
    }

    /**
     * The credential fields shown on the workspace connect form — the account
     * subset of configFields() (everything not marked scope 'site').
     *
     * @return array<int, ConfigField>
     */
    public function accountConfigFields(): array
    {
        return array_values(array_filter(
            $this->configFields(),
            fn (ConfigField $field): bool => $field->scope !== 'site',
        ));
    }

    /**
     * List the entities on a workspace connection (monitors, properties,
     * domains, billing contacts) so they can be matched per
     * {@see workspaceMapsTo()}. Only meaningful when
     * {@see supportsWorkspaceScope()} is true.
     *
     * @return array<int, DiscoveredConnection>
     */
    public function discoverConnections(WorkspaceIntegration $workspace): array
    {
        return [];
    }

    /**
     * The URL that starts this integration's OAuth handshake for a workspace
     * connection. Only meaningful when {@see AuthMethod::OAuth} is the auth
     * method; other integrations never call this.
     */
    public function oauthConnectUrl(WorkspaceIntegration $workspace): string
    {
        throw new LogicException($this->key().' does not support OAuth.');
    }

    /**
     * The collectors that gather data for this integration.
     *
     * @return array<int, Collector>
     */
    public function collectors(): array
    {
        return [];
    }

    /**
     * Report block definitions this integration registers. Wired in with the
     * reporting engine; empty by default.
     *
     * @return array<int, class-string>
     */
    public function reportBlocks(): array
    {
        return [];
    }

    /**
     * Pull a client-mapped billing connection's invoices into the local
     * {@see Invoice} ledger (upserted by external id, so re-running
     * is safe). Only meaningful for {@see workspaceMapsTo()} === 'client'
     * integrations; everything else is a no-op. Returns the number of invoices
     * written.
     */
    public function syncInvoices(ClientBillingConnection $link): int
    {
        return 0;
    }

    public function key(): string
    {
        return $this->manifest()->key;
    }
}
