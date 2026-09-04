<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ReportFrequency;
use App\Models\Report;
use App\Models\Site;
use App\Reporting\ReportComposer;
use App\Reporting\ReportGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class GenerateScheduledReports extends Command
{
    protected $signature = 'client-reporter:generate-scheduled';

    protected $description = 'Generate reports for scheduled sites whose latest period has closed';

    public function handle(ReportComposer $composer, ReportGenerator $generator): int
    {
        $now = CarbonImmutable::now();

        $sites = Site::query()
            ->where('is_active', true)
            ->where('report_frequency', '!=', ReportFrequency::None->value)
            ->with('reportTemplate')
            ->get();

        $generated = 0;
        $failed = 0;

        foreach ($sites as $site) {
            $period = $site->report_frequency->lastCompletedPeriod($now);

            if ($period === null) {
                continue;
            }

            // Skip if a report already covers this exact closed period (whether
            // scheduled or created by hand) — never duplicate.
            $exists = Report::query()
                ->where('site_id', $site->id)
                ->whereDate('range_start', $period->start->toDateString())
                ->whereDate('range_end', $period->end->toDateString())
                ->exists();

            if ($exists) {
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

            try {
                $generator->generate($report);
                $generated++;
            } catch (Throwable $e) {
                // Leave nothing half-made: drop the draft so the next run retries.
                $report->delete();
                $failed++;
                report($e);
                $this->warn("Failed to generate scheduled report for {$site->name}: {$e->getMessage()}");
            }
        }

        $this->info("Generated {$generated} scheduled report(s)".($failed > 0 ? ", {$failed} failed." : '.'));

        return self::SUCCESS;
    }
}
