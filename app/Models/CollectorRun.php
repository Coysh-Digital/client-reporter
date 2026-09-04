<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record of one collector execution, used to show sync health and diagnose
 * failures without exposing raw API internals.
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
