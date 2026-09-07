<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BackgroundTaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A single unit of background work — a report being generated, a connection
 * being collected, a billing sync, a favicon fetch — tracked so the sidebar and
 * the Activity page can show what is happening now (with a human label,
 * optional detail and progress) and what just finished.
 *
 * One row per logical task, keyed by {@see $task_key}, so re-queuing the same
 * work updates the row in place rather than piling up duplicates.
 *
 * @property int $id
 * @property string $kind
 * @property string|null $task_key
 * @property string $label
 * @property string|null $description
 * @property BackgroundTaskStatus $status
 * @property int|null $progress_current
 * @property int|null $progress_total
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $updated_at
 */
class BackgroundTask extends Model
{
    public const KIND_REPORT = 'report';

    public const KIND_COLLECTION = 'collection';

    public const KIND_BILLING = 'billing';

    public const KIND_FAVICON = 'favicon';

    protected $fillable = [
        'kind',
        'task_key',
        'label',
        'description',
        'status',
        'progress_current',
        'progress_total',
        'subject_type',
        'subject_id',
        'error',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BackgroundTaskStatus::class,
            'progress_current' => 'integer',
            'progress_total' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Record (or refresh) a task in the given state, keyed by $key so the same
     * logical work reuses its row.
     */
    public static function record(string $kind, string $key, BackgroundTaskStatus $status, string $label, ?string $description = null, ?Model $subject = null): self
    {
        $attributes = [
            'kind' => $kind,
            'label' => $label,
            'description' => $description,
            'status' => $status,
        ];

        if ($status === BackgroundTaskStatus::Queued) {
            $attributes['progress_current'] = null;
            $attributes['progress_total'] = null;
            $attributes['error'] = null;
            $attributes['started_at'] = null;
            $attributes['finished_at'] = null;
        }

        if ($subject !== null) {
            $attributes['subject_type'] = $subject->getMorphClass();
            $attributes['subject_id'] = $subject->getKey();
        }

        return self::query()->updateOrCreate(['task_key' => $key], $attributes);
    }

    public function markRunning(): self
    {
        $this->forceFill([
            'status' => BackgroundTaskStatus::Running,
            'started_at' => $this->started_at ?? now(),
            'finished_at' => null,
            'error' => null,
        ])->save();

        return $this;
    }

    public function setProgress(int $current, ?int $total = null): self
    {
        $this->forceFill([
            'progress_current' => max(0, $current),
            'progress_total' => $total !== null ? max(0, $total) : $this->progress_total,
        ])->save();

        return $this;
    }

    public function succeed(): self
    {
        $this->forceFill([
            'status' => BackgroundTaskStatus::Succeeded,
            'finished_at' => now(),
            'error' => null,
        ])->save();

        return $this;
    }

    public function fail(?string $error): self
    {
        $this->forceFill([
            'status' => BackgroundTaskStatus::Failed,
            'finished_at' => now(),
            'error' => $error,
        ])->save();

        return $this;
    }

    /** Whole-number percent complete, or null when there's no total to divide by. */
    public function percent(): ?int
    {
        if ($this->progress_total === null || $this->progress_total <= 0 || $this->progress_current === null) {
            return null;
        }

        return (int) min(100, round($this->progress_current / $this->progress_total * 100));
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<BackgroundTask>  $query
     * @return Builder<BackgroundTask>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', BackgroundTaskStatus::activeValues());
    }
}
