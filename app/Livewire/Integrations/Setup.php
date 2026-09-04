<?php

declare(strict_types=1);

namespace App\Livewire\Integrations;

use App\Enums\ConnectionStatus;
use App\Integrations\Contracts\Integration;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\AuthMethod;
use App\Integrations\Support\ConfigField;
use App\Models\Site;
use App\Models\SiteIntegration;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Setup extends Component
{
    public Site $site;

    public string $integrationKey = '';

    public ?int $connectionId = null;

    public string $name = '';

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
        $this->validateFields($fields, $existing);

        $credentials = $existing !== null ? ($existing->credentials ?? []) : [];
        $settings = $existing !== null ? ($existing->settings ?? []) : [];

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
        }

        $connection = $existing ?? new SiteIntegration([
            'site_id' => $this->site->id,
            'integration_key' => $integration->key(),
            'status' => ConnectionStatus::NotConnected,
        ]);

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

    /**
     * @param  array<int, ConfigField>  $fields
     */
    private function validateFields(array $fields, ?SiteIntegration $existing): void
    {
        $rules = [];
        $attributes = [];

        foreach ($fields as $field) {
            $rule = $field->validationRules();

            // On edit, a secret left blank means "keep existing".
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

    public function connectionCode(): ?string
    {
        $secret = $this->connection()?->credential('secret');

        return is_string($secret) && $secret !== '' ? $secret : null;
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
            'needsOAuthConnect' => $this->needsOAuthConnect(),
            'fields' => $this->fields($integration, $connection),
            'workspaceConnection' => $connection?->usesWorkspace() ? $connection->workspaceIntegration : null,
        ]);
    }
}
