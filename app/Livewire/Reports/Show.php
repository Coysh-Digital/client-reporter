<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Jobs\GenerateReport;
use App\Models\Report;
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

    public function generate(): void
    {
        $this->authorize('manage-reports');

        GenerateReport::queueFor($this->report, auth()->user());
        $this->report->refresh()->load('latestRender');
    }

    public function retryGeneration(): void
    {
        $this->generate();
    }

    /**
     * Polled while generation is in flight; refreshes the frozen render and
     * the page once it lands.
     */
    public function pollGeneration(): void
    {
        $wasGenerating = $this->report->isGenerating();
        $this->report->refresh()->load('latestRender');

        if ($wasGenerating && ! $this->report->isGenerating() && ! $this->report->generationFailed()) {
            session()->flash('status', 'Report generated.');
            $this->redirectRoute('reports.show', $this->report, navigate: true);
        }
    }

    public function render(): mixed
    {
        return view('livewire.reports.show');
    }
}
