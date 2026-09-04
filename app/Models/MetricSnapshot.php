<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A richer, integration-owned data payload for a period (top pages, product
 * lists, incident timelines). Kept separate from normalised metrics so reports
 * load quickly and remain available if an external API is temporarily down.
 *
 * @property array<string, mixed> $payload
 */
class MetricSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'site_integration_id',
        'collector_key',
        'period_start',
        'period_end',
        'granularity',
        'payload',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'payload' => 'array',
            'captured_at' => 'datetime',
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
