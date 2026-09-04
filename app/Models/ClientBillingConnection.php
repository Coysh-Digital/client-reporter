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
 */
class ClientBillingConnection extends Model
{
    protected $fillable = [
        'client_id',
        'workspace_integration_id',
        'external_contact_id',
        'external_contact_name',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
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
