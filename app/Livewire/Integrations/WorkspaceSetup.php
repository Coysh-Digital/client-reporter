<?php

declare(strict_types=1);

namespace App\Livewire\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\Contracts\Integration;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\AuthMethod;
use App\Integrations\Support\ConfigField;
use App\Integrations\Support\IntegrationException;
use App\Models\Client;
use App\Models\ClientBillingConnection;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Models\WorkspaceIntegration;
use App\Support\AuditLogger;
use App\Support\ClientMatcher;
use App\Support\SiteMatcher;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Connect an integration once for the whole workspace (one API key covering
 * every site or client), then auto-match the provider's entities by
 * {@see Integration::workspaceMapsTo()} — by URL to a site (analytics,
 * monitoring) or by email/name to a client (billing) — with manual override.
 * Two phases: 'credentials' then 'mapping'.
 */
#[Layout('components.layouts.app')]
class WorkspaceSetup extends Component
{
    public string $integrationKey = '';

    public ?int $workspaceId = null;

    public string $name = '';

    /** @var array<string, mixed> */
    public array $values = [];

    public string $phase = 'credentials';

    /** @var array<int, array{externalId: string, label: string, url: ?string, settings: array<string, mixed>, email: ?string}> */
    public array $discovered = [];

    /** @var array<int, int|string> Discovered index => site or client id (or '' to skip). */
    public array $assignments = [];

    public function mount(?string $key = null, ?WorkspaceIntegration $workspace = null): void
    {
        $this->authorize('manage-integrations');

        $registry = app(IntegrationRegistry::class);

        if ($workspace?->exists) {
            $this->workspaceId = $workspace->id;
            $integration = $workspace->integration();
        } else {
            $integration = $key ? $registry->find($key) : null;
        }

        abort_if($integration === null, 404, 'Integration not available.');
        abort_unless($integration->supportsWorkspaceScope(), 404, 'This integration is connected per site.');

        $this->integrationKey = $integration->key();

        $existing = $this->workspace();
        $this->name = $existing !== null ? $existing->name : $integration->manifest()->name.' (workspace)';

        foreach ($integration->accountConfigFields() as $field) {
            $this->values[$field->key] = ($existing !== null && ! $field->secret)
                ? (string) ($existing->setting($field->key) ?? '')
                : '';
        }
    }

    public function workspace(): ?WorkspaceIntegration
    {
        if ($this->workspaceId === null) {
            return null;
        }

        return WorkspaceIntegration::query()->whereKey($this->workspaceId)->first();
    }

    public function integration(): Integration
    {
        $integration = app(IntegrationRegistry::class)->find($this->integrationKey);

        abort_if($integration === null, 404, 'Integration not available.');

        return $integration;
    }

    /**
     * Phase 1 — save credentials, then discover and auto-match sites. OAuth
     * integrations (Google) have no account-level fields to fill in — the first
     * submit just saves the name and redirects to Google; the second (after the
     * callback stores a refresh token) proceeds to discovery.
     *
     * There is no site chosen yet at this point, so a normal per-site verify()
     * can't run (it needs a property/site id to query). Instead, discoverConnections()
     * itself — which calls the provider's account-scoped "list sites" API — is
     * the proof the credentials work: any exception there means bad credentials.
     */
    public function connect(): mixed
    {
        $this->authorize('manage-integrations');

        $integration = $this->integration();
        $isOAuth = $integration->manifest()->authMethod === AuthMethod::OAuth;
        $fields = $integration->accountConfigFields();
        $existing = $this->workspace();
        $this->validateFields($fields, $existing);

        $credentials = $existing !== null ? ($existing->credentials ?? []) : [];
        $settings = $existing !== null ? ($existing->settings ?? []) : [];

        foreach ($fields as $field) {
            $value = trim((string) ($this->values[$field->key] ?? ''));
            if ($field->secret) {
                if ($value !== '') {
                    $credentials[$field->key] = $value;
                }
            } else {
                $settings[$field->key] = $value !== '' ? $value : null;
            }
        }

        $workspace = $existing ?? new WorkspaceIntegration([
            'integration_key' => $integration->key(),
            'status' => ConnectionStatus::NotConnected,
        ]);
        $workspace->fill([
            'name' => $this->name,
            'credentials' => $credentials,
            'settings' => $settings,
        ])->save();
        $this->workspaceId = $workspace->id;

        if ($isOAuth && empty($workspace->credential('refresh_token'))) {
            return $this->redirect($integration->oauthConnectUrl($workspace));
        }

        try {
            $this->buildMapping($integration, $workspace);
        } catch (IntegrationException $e) {
            $workspace->update(['status' => ConnectionStatus::Error, 'last_error' => $e->getMessage()]);
            $this->addError('verification', $e->getMessage());

            return null;
        }

        $workspace->update(['status' => ConnectionStatus::Connected, 'last_connected_at' => now(), 'last_error' => null]);
        $this->phase = 'mapping';

        return null;
    }

    private function buildMapping(Integration $integration, WorkspaceIntegration $workspace): void
    {
        $entities = $integration->discoverConnections($workspace);

        $matches = $integration->workspaceMapsTo() === 'client'
            ? ClientMatcher::match($entities, Client::query()->get())
            : SiteMatcher::match($entities, Site::query()->get());

        $this->discovered = [];
        $this->assignments = [];
        foreach ($entities as $index => $entity) {
            $this->discovered[$index] = [
                'externalId' => $entity->externalId,
                'label' => $entity->label,
                'url' => $entity->url,
                'settings' => $entity->settings,
                'email' => $entity->email,
            ];
            $this->assignments[$index] = $matches[$index] ?? '';
        }
    }

    /**
     * Mark every still-unmapped discovered contact to be created as a new
     * client. A shortcut for the common case of importing a whole FreeAgent
     * account's worth of contacts as clients in one go.
     */
    public function createNewForUnmapped(): void
    {
        foreach (array_keys($this->discovered) as $index) {
            if (($this->assignments[$index] ?? '') === '') {
                $this->assignments[$index] = 'new';
            }
        }
    }

    /**
     * Phase 2 — create/update connections from the confirmed mapping. Maps to
     * sites (analytics, monitoring) or clients (billing) per
     * {@see Integration::workspaceMapsTo()}.
     */
    public function confirm(AuditLogger $audit): mixed
    {
        $this->authorize('manage-integrations');

        $integration = $this->integration();
        $workspace = $this->workspace();
        abort_if($workspace === null, 404);

        $created = $integration->workspaceMapsTo() === 'client'
            ? $this->confirmClientMappings($integration, $workspace)
            : $this->confirmSiteMappings($workspace);

        $noun = $integration->workspaceMapsTo() === 'client' ? 'client' : 'site';

        $audit->log('integration.workspace_connected', $workspace, metadata: [
            'integration' => $this->integrationKey,
            $noun.'s' => $created,
        ]);

        session()->flash('status', $created > 0
            ? "Connected {$created} ".str($noun)->plural($created).' from the workspace connection.'
            : 'Workspace connection saved. No matches were mapped yet.');

        return $this->redirectRoute('integrations.index', navigate: true);
    }

    private function confirmSiteMappings(WorkspaceIntegration $workspace): int
    {
        $created = 0;
        foreach ($this->discovered as $index => $entity) {
            $siteId = $this->assignments[$index] ?? '';
            if ($siteId === '') {
                continue;
            }

            $connection = SiteIntegration::query()->firstOrNew([
                'site_id' => (int) $siteId,
                'integration_key' => $this->integrationKey,
            ]);

            $connection->fill([
                'workspace_integration_id' => $workspace->id,
                'name' => $entity['label'],
                'credentials' => null,
                'settings' => $entity['settings'],
                'status' => ConnectionStatus::Connected,
                'last_connected_at' => now(),
                'last_error' => null,
            ])->save();

            $created++;
        }

        return $created;
    }

    private function confirmClientMappings(Integration $integration, WorkspaceIntegration $workspace): int
    {
        $created = 0;
        foreach ($this->discovered as $index => $entity) {
            $assignment = $this->assignments[$index] ?? '';
            if ($assignment === '') {
                continue;
            }

            // "new" creates a fresh client from the discovered contact; anything
            // else is the id of an existing client to map onto.
            $clientId = $assignment === 'new'
                ? Client::query()->create([
                    'name' => $entity['label'],
                    'contact_email' => $entity['email'],
                ])->id
                : (int) $assignment;

            $link = ClientBillingConnection::query()->updateOrCreate(
                ['client_id' => $clientId],
                [
                    'workspace_integration_id' => $workspace->id,
                    'external_contact_id' => $entity['externalId'],
                    'external_contact_name' => $entity['label'],
                ],
            );

            try {
                $integration->syncInvoices($link);
                $link->update(['last_synced_at' => now()]);
            } catch (IntegrationException) {
                // The mapping is saved either way; a failed first sync just
                // means it will try again on the next scheduled sync.
            }

            $created++;
        }

        return $created;
    }

    /**
     * @param  array<int, ConfigField>  $fields
     */
    private function validateFields(array $fields, ?WorkspaceIntegration $existing): void
    {
        $rules = [];
        $attributes = [];

        foreach ($fields as $field) {
            $rule = $field->validationRules();
            if ($field->secret && $existing !== null) {
                $rule = array_map(fn ($r) => $r === 'required' ? 'nullable' : $r, $rule);
            }
            $rules["values.{$field->key}"] = $rule;
            $attributes["values.{$field->key}"] = $field->label;
        }

        Validator::make(
            ['values' => $this->values, 'name' => $this->name],
            array_merge($rules, ['name' => ['required', 'string', 'max:255']]),
            [],
            $attributes,
        )->validate();
    }

    public function needsOAuthConnect(): bool
    {
        $integration = $this->integration();

        return $integration->manifest()->authMethod === AuthMethod::OAuth
            && empty($this->workspace()?->credential('refresh_token'));
    }

    public function render(): mixed
    {
        $integration = $this->integration();
        $mapsToClient = $integration->workspaceMapsTo() === 'client';

        return view('livewire.integrations.workspace-setup', [
            'integration' => $integration,
            'mapsToClient' => $mapsToClient,
            'options' => $mapsToClient
                ? Client::query()->orderBy('name')->get(['id', 'name'])
                : Site::query()->orderBy('name')->get(['id', 'name', 'url']),
            'matchedCount' => collect($this->assignments)->filter(fn ($v) => $v !== '')->count(),
            'needsOAuthConnect' => $this->needsOAuthConnect(),
        ]);
    }
}
