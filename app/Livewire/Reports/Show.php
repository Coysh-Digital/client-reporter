<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\Report;
use App\Reporting\ReportGenerator;
use App\Support\AuditLogger;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public Report $report;

    public function mount(Report $report): void
    {
        $this->report = $report->load('site.client', 'latestRender');
    }

    public function generate(ReportGenerator $generator, AuditLogger $audit): void
    {
        $this->authorize('manage-reports');

        $generator->generate($this->report);
        $audit->log('report.generated', $this->report);

        $this->report->refresh()->load('latestRender');
        session()->flash('status', 'Report generated.');
    }

    public function render(): mixed
    {
        return view('livewire.reports.show');
    }
}
