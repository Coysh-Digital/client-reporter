<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A recurring invoice schedule synced from a billing connection (e.g.
 * FreeAgent) — a template that raises an invoice every period, not an invoice
 * itself. Kept apart from {@see Invoice} so it can show what's coming up without
 * ever being counted in a client's reports.
 *
 * @property int $id
 * @property int $client_id
 * @property string $source
 * @property string $external_id
 * @property string|null $reference
 * @property string|null $frequency
 * @property string|null $status
 * @property float $amount
 * @property string|null $currency
 * @property Carbon|null $next_recurs_on
 * @property Carbon|null $ends_on
 */
class RecurringInvoice extends Model
{
    protected $fillable = [
        'client_id',
        'source',
        'external_id',
        'reference',
        'frequency',
        'status',
        'amount',
        'currency',
        'next_recurs_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'next_recurs_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isActive(): bool
    {
        return strtolower((string) $this->status) === 'active';
    }
}
