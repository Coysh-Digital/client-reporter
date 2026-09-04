<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConnectionStatus;
use App\Integrations\Contracts\Integration;
use App\Integrations\IntegrationRegistry;
use Database\Factories\SiteIntegrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A configured connection between a Site and an integration (e.g. this site's
 * UptimeRobot account). Credentials are encrypted at rest.
 *
 * @property int $id
 * @property int $site_id
 * @property int|null $workspace_integration_id
 * @property string $integration_key
 * @property ConnectionStatus $status
 * @property array<string, mixed>|null $credentials
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $last_connected_at
 * @property Carbon|null $last_collected_at
 * @property string|null $last_error
 * @property string|null $connector_version
 */
class SiteIntegration extends Model
{
    /** @use HasFactory<SiteIntegrationFactory> */
    use HasFactory;

    protected $fillable = [
        'site_id',
        'workspace_integration_id',
        'integration_key',
        'name',
        'status',
        'credentials',
        'settings',
        'connector_version',
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
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * The workspace-wide connection this site connection borrows credentials
     * from, if any.
     *
     * @return BelongsTo<WorkspaceIntegration, $this>
     */
    public function workspaceIntegration(): BelongsTo
    {
        return $this->belongsTo(WorkspaceIntegration::class);
    }

    /**
     * Whether this connection draws its credentials from a workspace-wide one.
     */
    public function usesWorkspace(): bool
    {
        return $this->workspace_integration_id !== null;
    }

    /**
     * @return HasMany<CollectorRun, $this>
     */
    public function collectorRuns(): HasMany
    {
        return $this->hasMany(CollectorRun::class);
    }

    /**
     * @return HasMany<Metric, $this>
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(Metric::class);
    }

    /**
     * @return HasMany<MetricSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(MetricSnapshot::class);
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
        $local = ($this->credentials ?? [])[$key] ?? null;
        if ($local !== null) {
            return $local;
        }

        // Workspace-linked connections borrow their credentials (API keys,
        // tokens) from the shared workspace connection; per-site settings such
        // as the matched monitor/property stay local.
        if ($this->workspace_integration_id !== null) {
            return $this->workspaceIntegration?->credential($key, $default) ?? $default;
        }

        return $default;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }
}
