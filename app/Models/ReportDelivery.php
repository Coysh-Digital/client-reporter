<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryTrigger;
use Database\Factories\ReportDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A record of a report being emailed — manually from the share panel or
 * automatically by the scheduler. Both successes and failures are kept, so the
 * report page and the Scheduled reports page can show a history of what went
 * out (and what didn't).
 *
 * @property int $id
 * @property int $report_id
 * @property string $recipient
 * @property DeliveryTrigger $trigger
 * @property bool $included_pdf
 * @property bool $succeeded
 * @property string|null $error
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ReportDelivery extends Model
{
    /** @use HasFactory<ReportDeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'report_id',
        'recipient',
        'trigger',
        'included_pdf',
        'succeeded',
        'error',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'trigger' => DeliveryTrigger::class,
            'included_pdf' => 'boolean',
            'succeeded' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Report, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
