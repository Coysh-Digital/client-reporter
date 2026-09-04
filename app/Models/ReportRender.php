<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A frozen snapshot of a report's resolved block data and branding at the moment
 * it was generated.
 *
 * @property array<string, mixed> $data
 * @property array<string, mixed> $branding_snapshot
 * @property array<string, mixed>|null $meta
 */
class ReportRender extends Model
{
    protected $fillable = [
        'report_id',
        'rendered_at',
        'data',
        'branding_snapshot',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'rendered_at' => 'datetime',
            'data' => 'array',
            'branding_snapshot' => 'array',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Report, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
