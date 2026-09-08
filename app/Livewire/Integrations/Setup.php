<?php

declare(strict_types=1);

namespace App\Livewire\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\Contracts\Integration;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\AuthMethod;
use App\Integrations\Support\ConfigField;
use App\Livewire\Concerns\ValidatesConfigFields;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Support\AuditLogger;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Setup extends Component
{
    use ValidatesConfigFields;

    public Site $site;

    public string $integrationKey = '';

    public ?int $connectionId = null;

    public string $name = '';

    /** Per-connection collection interval in minutes; '' means inherit. */
    public string $collectionInterval = '';

    /** @var array<string, mixed> */
    public array $values = [];

    public function mount(?Site $site = null, ?string $key = null, ?SiteIntegration $connection = null): void
    {
        $this->authorize('manage-integrations');

        $registry = app(IntegrationRegistry::class);

        if ($connection?->exists) {
            $this->connectionId = $connection->id;
            $this->site = $connection->site;
            $integration = $connection->integration();
        } else {
            abort_unless($site?->exists, 404);
            $this->site = $site;
            $integration = $key ? $registry->find($key) : null;

            // If this integration is already connected on the site (for example
            // a workspace connection discovered it), edit that connection rather
            // than creating a second one — (site, integration) is unique.
            if ($integration !== null) {
                $this->connectionId = SiteIntegration::query()
                    ->where('site_id', $site->id)
                    ->where('integration_key', $integration->key())
                    ->value('id');
            }
        }

        abort_if($integration === null, 404, 'Integration not available.');
        $this->integrationKey = $integration->key();

        $existing = $this->connection();
        $this->name = $existing !== null ? $existing->name : $integration->manifest()->name;

        // Prefill non-secret settings; secrets are never sent back to the browser.
        foreach ($this->fields($integration, $existing) as $field) {
            $this->values[$field->key] = ($existing !== null && ! $field->secret)
                ? (string) ($existing->setting($field->key) ?? '')
                : '';
        }

        $this->collectionInterval = $existing !== null ? (string) ($existing->setting('collection_interval') ?? '') : '';
    }

    /**
     * Collection-frequency presets for the select, value (minutes) => label.
     *
     * @return array<int|string, string>
     */
    public function frequencyOptions(): array
    {
        return [
            '' => 'Use default',
            '60' => 'Hourly',
            '180' => 'Every 3 hours',
            '360' => 'Every 6 hours',
            '720' => 'Every 12 hours',
            '1440' => 'Daily',
        ];
    }

    /**
     * The fields this form should show and save. A connection borrowing
     * credentials from a workspace-wide connection only exposes its per-site
     * fields (e.g. which monitor) — account fields (API keys, tokens) are
     * managed once on the workspace connection, never duplicated here.
     *
     * @return array<int, ConfigField>
     */
    private function fields(Integration $integration, ?SiteIntegration $existing): array
    {
        if ($existing?->usesWorkspace()) {
            return array_values(array_filter(
                $integration->configFields(),
                fn (ConfigField $field): bool => $field->scope === 'site',
            ));
        }

        return $integration->configFields();
    }

    public function connection(): ?SiteIntegration
    {
        if ($this->connectionId === null) {
            return null;
        }

        return SiteIntegration::query()->whereKey($this->connectionId)->first();
    }

    public function integration(): Integration
    {
        $integration = app(IntegrationRegistry::class)->find($this->integrationKey);

        abort_if($integration === null, 404, 'Integration not available.');

        return $integration;
    }

    public function save(AuditLogger $audit): mixed
    {
        $this->authorize('manage-integrations');

        $integration = $this->integration();
        $existing = $this->connection();
        $fields = $this->fields($integration, $existing);
        $this->validateConfigFields($fields, $this->values, $this->name, $existing !== null);
        $this->validate(['collectionInterval' => ['nullable', 'in:60,180,360,720,1440']]);

        $credentials = $existing !== null ? ($existing->credentials ?? []) : [];
        $settings = $existing !== null ? ($existing->settings ?? []) : [];
        $settings['collection_interval'] = $this->collectionInterval !== '' ? (int) $this->collectionInterval : null;

        foreach ($fields as $field) {
            $value = trim((string) ($this->values[$field->key] ?? ''));

            if ($field->secret) {
                // Keep the existing secret if left blank when editing.
                if ($value !== '') {
                    $credentials[$field->key] = $value;
                }
            } else {
                $settings[$field->key] = $value !== '' ? $value : null;
            }
        }

        // Companion-connector integrations authenticate with a generated shared
        // secret (the "connection code"), created once and shown to the user to
        // paste into the plugin.
        if ($integration->manifest()->authMethod === AuthMethod::ConnectorToken && empty($credentials['secret'])) {
            $credentials['secret'] = Str::random(48);
            $this->revealConnectionCode($credentials['secret']);
        }

        // firstOrNew on the unique (site, integration) pair, so a connection the
        // form didn't already load (e.g. one discovered by a workspace
        // connection) is updated rather than duplicated into a constraint error.
        $connection = $existing ?? SiteIntegration::query()->firstOrNew([
            'site_id' => $this->site->id,
            'integration_key' => $integration->key(),
        ]);

        if (! $connection->exists) {
            $connection->status = ConnectionStatus::NotConnected;
        }

        $connection->fill([
            'name' => $this->name,
            'credentials' => $credentials,
            'settings' => $settings,
        ])->save();

        $this->connectionId = $connection->id;

        // OAuth integrations need the account connected before they can verify.
        if ($integration->manifest()->authMethod === AuthMethod::OAuth && empty($connection->credential('refresh_token'))) {
            session()->flash('status', 'Saved. Now connect your account to finish.');

            return $this->redirectRoute('integrations.edit', $connection, navigate: true);
        }

        // Verify the credentials against the service and reflect the result.
        $result = $integration->verify($connection);
        $connection->update([
            'status' => $result->ok ? ConnectionStatus::Connected : ConnectionStatus::Error,
            'last_connected_at' => $result->ok ? now() : $connection->last_connected_at,
            'last_error' => $result->ok ? null : $result->message,
            'connector_version' => $result->meta['connector_version'] ?? $connection->connector_version,
            // A successful verify clears any auto-disable from earlier failures.
            'consecutive_failures' => $result->ok ? 0 : $connection->consecutive_failures,
            'disabled_at' => $result->ok ? null : $connection->disabled_at,
        ]);

        $audit->log('integration.connected', $connection, metadata: [
            'integration' => $connection->integration_key,
            'ok' => $result->ok,
        ]);

        if (! $result->ok) {
            $this->addError('verification', $result->message);

            return null;
        }

        session()->flash('status', $result->message);

        return $this->redirectRoute('sites.show', $this->site, navigate: true);
    }

    public function isConnectorBased(): bool
    {
        return $this->integration()->manifest()->authMethod === AuthMethod::ConnectorToken;
    }

    public function isOAuth(): bool
    {
        return $this->integration()->manifest()->authMethod === AuthMethod::OAuth;
    }

    public function needsOAuthConnect(): bool
    {
        $connection = $this->connection();

        return $this->isOAuth() && $connection !== null && empty($connection->credential('refresh_token'));
    }

    /**
     * The connection code is shown once — right after it is generated — and
     * masked afterwards. Regenerating issues a fresh code (the plugin must be
     * updated to match) and reveals it again.
     */
    public function connectionCode(): ?string
    {
        $secret = session('connection_code');

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    public function hasConnectionCode(): bool
    {
        $secret = $this->connection()?->credential('secret');

        return is_string($secret) && $secret !== '';
    }

    public function regenerateConnectionCode(AuditLogger $audit): void
    {
        $this->authorize('manage-integrations');

        $connection = $this->connection();
        if ($connection === null || ! $this->isConnectorBased()) {
            return;
        }

        $credentials = $connection->credentials ?? [];
        $credentials['secret'] = Str::random(48);

        $connection->update([
            'credentials' => $credentials,
            'status' => ConnectionStatus::NotConnected,
            'last_error' => null,
        ]);

        $audit->log('integration.connection_code_rotated', $connection, metadata: ['integration' => $connection->integration_key]);
        $this->revealConnectionCode($credentials['secret']);
    }

    private function revealConnectionCode(string $secret): void
    {
        // Flashed: readable while this request renders and on the next one.
        session()->flash('connection_code', $secret);
    }

    public function render(): mixed
    {
        $connection = $this->connection();
        $integration = $this->integration();

        return view('livewire.integrations.setup', [
            'connection' => $connection,
            'integration' => $integration,
            'isConnector' => $this->isConnectorBased(),
            'connectionCode' => $this->connectionCode(),
            'hasConnectionCode' => $this->hasConnectionCode(),
            'needsOAuthConnect' => $this->needsOAuthConnect(),
            'fields' => $this->fields($integration, $connection),
            'workspaceConnection' => $connection?->usesWorkspace() ? $connection->workspaceIntegration : null,
        ]);
    }
}
