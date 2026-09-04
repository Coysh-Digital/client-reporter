<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A stored normalised metric value for a period. The public $timestamps are off;
 * `captured_at` records when the value was collected.
 *
 * @property string $metric_key
 * @property float $value
 * @property string|null $unit
 * @property Carbon $period_start
 * @property Carbon $period_end
 */
class Metric extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'site_integration_id',
        'metric_key',
        'period_start',
        'period_end',
        'value',
        'unit',
        'meta',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'value' => 'float',
            'meta' => 'array',
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
