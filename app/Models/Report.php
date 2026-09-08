<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Enums\ReportPeriodStatus;
use App\Support\DateRange;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $site_id
 * @property string $title
 * @property string $status
 * @property bool $scheduled
 * @property Carbon|null $scheduled_for
 * @property Carbon $range_start
 * @property Carbon $range_end
 * @property bool $compare_previous
 * @property Carbon|null $generated_at
 * @property GenerationStatus|null $generation_status
 * @property Carbon|null $generation_queued_at
 * @property Carbon|null $generation_started_at
 * @property string|null $generation_error
 * @property int|null $shares_count
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
        'scheduled',
        'scheduled_for',
        'intro',
        'created_by',
        'generated_at',
        'generation_status',
        'generation_queued_at',
        'generation_started_at',
        'generation_error',
    ];

    protected function casts(): array
    {
        return [
            'range_start' => 'date',
            'range_end' => 'date',
            'compare_previous' => 'boolean',
            'scheduled' => 'boolean',
            'scheduled_for' => 'date',
            'generated_at' => 'datetime',
            'generation_status' => GenerationStatus::class,
            'generation_queued_at' => 'datetime',
            'generation_started_at' => 'datetime',
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
     * @return HasMany<ReportDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(ReportDelivery::class);
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

    /**
     * A draft set to auto-generate on a specific date that hasn't generated yet.
     */
    public function isAwaitingScheduledGeneration(): bool
    {
        return $this->scheduled_for !== null && ! $this->isGenerated();
    }

    public function isGenerating(): bool
    {
        return $this->generation_status?->isInProgress() ?? false;
    }

    public function generationFailed(): bool
    {
        return $this->generation_status === GenerationStatus::Failed;
    }

    /**
     * Where this report stands for its period: draft, generated-but-unsent
     * ("ready"), or sent (it has at least one share link / email). Uses the
     * `shares_count` aggregate when the query loaded it, so lists stay at one
     * query.
     */
    public function periodStatus(): ReportPeriodStatus
    {
        if (! $this->isGenerated() && $this->status !== 'final') {
            return ReportPeriodStatus::Draft;
        }

        $shares = $this->shares_count ?? $this->shares()->count();

        return $shares > 0 ? ReportPeriodStatus::Sent : ReportPeriodStatus::Ready;
    }
}
