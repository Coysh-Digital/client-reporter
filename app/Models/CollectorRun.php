<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A record of one collector execution, used to show sync health and diagnose
 * failures without exposing raw API internals.
 *
 * @property int $id
 * @property int $site_integration_id
 * @property string $collector_key
 * @property string $status
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $duration_ms
 * @property int|null $records_written
 * @property string|null $error_message
 */
class CollectorRun extends Model
{
    protected $fillable = [
        'site_integration_id',
        'collector_key',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'records_written',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'records_written' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SiteIntegration, $this>
     */
    public function siteIntegration(): BelongsTo
    {
        return $this->belongsTo(SiteIntegration::class);
    }
}
