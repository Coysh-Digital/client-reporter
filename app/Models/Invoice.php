<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An invoice the agency raised against a client — manually entered so it works
 * regardless of which accounting tool the agency actually bills through.
 *
 * @property int $id
 * @property int $client_id
 * @property string $source
 * @property string|null $external_id
 * @property string $number
 * @property string|null $description
 * @property float $amount
 * @property string|null $currency
 * @property InvoiceStatus $status
 * @property Carbon $issued_at
 * @property Carbon|null $due_at
 * @property Carbon|null $paid_at
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'source',
        'external_id',
        'number',
        'description',
        'amount',
        'currency',
        'status',
        'issued_at',
        'due_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'amount' => 'decimal:2',
            'issued_at' => 'date',
            'due_at' => 'date',
            'paid_at' => 'date',
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
     * A Sent invoice past its due date — derived, never stored, so it can't
     * go stale.
     */
    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Sent
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    /**
     * Whether this invoice was synced from a billing connection (FreeAgent,
     * Xero) rather than entered by hand — synced invoices are read-only in the
     * app, since the source of truth lives in the accounting system.
     */
    public function isSynced(): bool
    {
        return $this->source !== 'manual';
    }
}
