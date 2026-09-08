<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\GenerationStatus;
use App\Enums\ReportFrequency;
use App\Jobs\GenerateReport;
use App\Models\Report;
use App\Models\Site;
use App\Reporting\ReportComposer;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Composes a report for every scheduled site whose latest period has closed
 * and queues its generation. Generation itself runs one job per report, so a
 * slow integration on one site never holds up the rest or the scheduler.
 */
class GenerateScheduledReports extends Command
{
    protected $signature = 'client-reporter:generate-scheduled';

    protected $description = 'Queue reports for scheduled sites whose latest period has closed';

    public function handle(ReportComposer $composer): int
    {
        $now = CarbonImmutable::now();

        $sites = Site::query()
            ->where('is_active', true)
            ->where('report_frequency', '!=', ReportFrequency::None->value)
            ->with('reportTemplate')
            ->get();

        $queued = 0;
        $retried = 0;

        foreach ($sites as $site) {
            $period = $site->report_frequency->lastCompletedPeriod($now);

            if ($period === null) {
                continue;
            }

            // Respect the site's settle delay: hold generation until the
            // configured number of days after the period closed, giving
            // analytics time to catch up. A delay of 4 on a monthly site
            // generates last month's report on the 5th, not the 1st.
            if ($now->lessThan($period->end->addDays($site->generationDelayDays()))) {
                continue;
            }

            // A report already covering this exact closed period (scheduled or
            // hand-made) is never duplicated — but a scheduled one whose
            // generation failed gets another go on the next run.
            $existing = Report::query()
                ->where('site_id', $site->id)
                ->whereDate('range_start', $period->start->toDateString())
                ->whereDate('range_end', $period->end->toDateString())
                ->first();

            if ($existing !== null) {
                if ($existing->scheduled && ! $existing->isGenerated() && $existing->generationFailed()) {
                    GenerateReport::queueFor($existing);
                    $retried++;
                }

                continue;
            }

            $report = $composer->compose(
                site: $site,
                range: $period,
                title: $period->label().' report',
                template: $site->reportTemplate,
                comparePrevious: true,
                createdBy: null,
                scheduled: true,
            );

            GenerateReport::queueFor($report);
            $queued++;
        }

        $dated = $this->queueDatedReports($now);

        $this->info("Queued {$queued} scheduled report(s)".($retried > 0 ? ", retried {$retried} failed one(s)" : '').($dated > 0 ? ", {$dated} one-off dated report(s)." : '.'));

        return self::SUCCESS;
    }

    /**
     * Queue any one-off report set to auto-generate on or before today that
     * hasn't generated yet and isn't already in flight. A failed one is picked
     * up again on the next run; a generated one drops out (generated_at is set).
     */
    private function queueDatedReports(CarbonImmutable $now): int
    {
        $reports = Report::query()
            ->whereNotNull('scheduled_for')
            ->whereNull('generated_at')
            ->whereDate('scheduled_for', '<=', $now->toDateString())
            // A fresh draft has a null status; skip only ones already in flight.
            ->where(fn ($query) => $query
                ->whereNull('generation_status')
                ->orWhereNotIn('generation_status', [GenerationStatus::Queued, GenerationStatus::Running]))
            ->get();

        foreach ($reports as $report) {
            GenerateReport::queueFor($report);
        }

        return $reports->count();
    }
}
