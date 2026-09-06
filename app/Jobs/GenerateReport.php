<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Report;
use App\Models\User;
use App\Reporting\ReportGenerator;
use App\Support\AuditLogger;
use App\Support\SafeError;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Generates a report in the background. Generation collects fresh data from
 * every integration the report needs (slow, external) before resolving and
 * freezing the render, so it never runs inside a web request. The report's
 * generation_status lets the builder and report pages poll for the outcome.
 *
 * One job per report at a time: a second request while one is queued or
 * running is dropped by the unique lock rather than doubling the work.
 */
class GenerateReport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 900;

    public function __construct(
        public Report $report,
        public ?int $userId = null,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->report->id;
    }

    /**
     * Mark the report as queued and dispatch. The single entry point for
     * every caller (builder, report page, scheduler).
     */
    public static function queueFor(Report $report, ?User $actor = null): void
    {
        $report->forceFill([
            'generation_status' => GenerationStatus::Queued,
            'generation_queued_at' => now(),
            'generation_started_at' => null,
            'generation_error' => null,
        ])->save();

        self::dispatch($report, $actor?->id);
    }

    public function handle(ReportGenerator $generator, AuditLogger $audit): void
    {
        $report = $this->report->fresh(['blocks']);

        if ($report === null) {
            return;
        }

        $report->forceFill([
            'generation_status' => GenerationStatus::Running,
            'generation_started_at' => now(),
        ])->save();

        $generator->generate($report);

        $report->forceFill(['generation_status' => null, 'generation_error' => null])->save();

        $actor = $this->userId !== null ? User::query()->whereKey($this->userId)->first() : null;
        $audit->log('report.generated', $report, $actor, metadata: ['scheduled' => $report->scheduled]);
    }

    public function failed(?Throwable $e): void
    {
        $this->report->forceFill([
            'generation_status' => GenerationStatus::Failed,
            'generation_error' => $e !== null ? SafeError::message($e, 'Generation failed unexpectedly') : 'Generation failed.',
        ])->save();
    }
}
