<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportFrequency;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;

/**
 * @property ReportFrequency $report_frequency
 * @property int|null $report_template_id
 */
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'url',
        'favicon_path',
        'favicon_fetched_at',
        'cms_type',
        'environment',
        'timezone',
        'is_active',
        'settings',
        'report_frequency',
        'report_template_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'report_frequency' => ReportFrequency::class,
            'favicon_fetched_at' => 'datetime',
        ];
    }

    /**
     * The public URL of this site's cached favicon, or null if none has been
     * fetched yet (callers fall back to a letter avatar).
     */
    public function faviconUrl(): ?string
    {
        return $this->favicon_path
            ? Storage::disk('public')->url($this->favicon_path)
            : null;
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Optional branding override for this site.
     *
     * @return MorphOne<BrandingProfile, $this>
     */
    public function branding(): MorphOne
    {
        return $this->morphOne(BrandingProfile::class, 'brandable');
    }

    /**
     * Configured integration connections for this site.
     *
     * @return HasMany<SiteIntegration, $this>
     */
    public function integrations(): HasMany
    {
        return $this->hasMany(SiteIntegration::class);
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * The template scheduled reports are built from (optional; falls back to a
     * default set of blocks when none is chosen).
     *
     * @return BelongsTo<ReportTemplate, $this>
     */
    public function reportTemplate(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class);
    }

    /**
     * Whether this site is on a reporting schedule.
     */
    public function hasReportSchedule(): bool
    {
        return $this->report_frequency->isScheduled();
    }

    /**
     * The host portion of the site URL, for compact display.
     */
    public function host(): string
    {
        return (string) (parse_url($this->url, PHP_URL_HOST) ?: $this->url);
    }
}
