<?php

declare(strict_types=1);

namespace App\Models;

use App\Billing\BillingSyncer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Links a client to a contact/customer on a workspace-wide billing connection
 * (FreeAgent, Xero) — the counterpart to a site's monitor/property mapping,
 * but for clients. {@see BillingSyncer} uses this to pull that
 * contact's invoices into the local {@see Invoice} ledger.
 *
 * @property int $id
 * @property int $client_id
 * @property int $workspace_integration_id
 * @property string $external_contact_id
 * @property string $external_contact_name
 * @property Carbon|null $last_synced_at
 * @property int $consecutive_failures
 * @property Carbon|null $last_attempted_at
 * @property string|null $last_error
 * @property Carbon|null $disabled_at
 */
class ClientBillingConnection extends Model
{
    protected $fillable = [
        'client_id',
        'workspace_integration_id',
        'external_contact_id',
        'external_contact_name',
        'last_synced_at',
        'consecutive_failures',
        'last_attempted_at',
        'last_error',
        'disabled_at',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'consecutive_failures' => 0,
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'consecutive_failures' => 'integer',
            'last_attempted_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    /**
     * Whether this billing link has been auto-disabled after repeated sync
     * failures and is being skipped until it's reconnected.
     */
    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<WorkspaceIntegration, $this>
     */
    public function workspaceIntegration(): BelongsTo
    {
        return $this->belongsTo(WorkspaceIntegration::class);
    }
}
