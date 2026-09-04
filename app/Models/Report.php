<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\DateRange;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $range_start
 * @property Carbon $range_end
 * @property bool $compare_previous
 */
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    protected $fillable = [
        'site_id',
        'report_template_id',
        'title',
        'range_start',
        'range_end',
        'compare_previous',
        'status',
        'intro',
        'created_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'range_start' => 'date',
            'range_end' => 'date',
            'compare_previous' => 'boolean',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<ReportTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'report_template_id');
    }

    /**
     * @return HasMany<ReportBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(ReportBlock::class)->orderBy('position');
    }

    /**
     * @return HasMany<ReportShare, $this>
     */
    public function shares(): HasMany
    {
        return $this->hasMany(ReportShare::class);
    }

    /**
     * @return HasOne<ReportRender, $this>
     */
    public function latestRender(): HasOne
    {
        return $this->hasOne(ReportRender::class)->latestOfMany('rendered_at');
    }

    public function dateRange(): DateRange
    {
        return new DateRange($this->range_start, $this->range_end);
    }

    public function comparisonRange(): ?DateRange
    {
        return $this->compare_previous ? $this->dateRange()->previous() : null;
    }

    public function isGenerated(): bool
    {
        return $this->generated_at !== null;
    }
}
