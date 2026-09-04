<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConnectionStatus;
use App\Integrations\Contracts\Integration;
use App\Integrations\IntegrationRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A workspace-wide (account-level) connection to an integration — one set of
 * credentials (e.g. a single UptimeRobot API key) that many site connections
 * can share. Credentials are encrypted at rest.
 *
 * @property int $id
 * @property string $integration_key
 * @property string $name
 * @property ConnectionStatus $status
 * @property array<string, mixed>|null $credentials
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $last_connected_at
 * @property Carbon|null $last_collected_at
 * @property string|null $last_error
 */
class WorkspaceIntegration extends Model
{
    protected $fillable = [
        'integration_key',
        'name',
        'status',
        'credentials',
        'settings',
        'last_connected_at',
        'last_collected_at',
        'last_error',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'status' => ConnectionStatus::class,
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'last_connected_at' => 'datetime',
            'last_collected_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SiteIntegration, $this>
     */
    public function siteIntegrations(): HasMany
    {
        return $this->hasMany(SiteIntegration::class);
    }

    /**
     * The integration definition backing this connection, or null if its
     * package is no longer installed.
     */
    public function integration(): ?Integration
    {
        return app(IntegrationRegistry::class)->find($this->integration_key);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return ($this->credentials ?? [])[$key] ?? $default;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }
}
