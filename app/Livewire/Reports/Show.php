<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Enums\ReportFrequency;
use App\Jobs\GenerateReport;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Reporting\ReportDuplicator;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public Report $report;

    public string $report_frequency = 'none';

    public ?int $report_template_id = null;

    /** '' = use the workspace default, 'yes' = always send, 'no' = never. */
    public string $auto_send = '';

    /** Days to wait after a period closes before generating (0 = immediately). */
    public int $report_generation_delay_days = 0;

    public function mount(Report $report): void
    {
        $this->report = $report->load('site.client', 'latestRender');
        $this->report_frequency = $this->report->site->report_frequency->value;
        $this->report_template_id = $this->report->site->report_template_id;
        $this->auto_send = match ($this->report->site->autoSendSetting()) {
            true => 'yes', false => 'no', default => ''
        };
        $this->report_generation_delay_days = $this->report->site->generationDelayDays();
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
     * Copy this report's sections into a fresh draft and open it in the builder,
     * ready to change the date range and generate for another period.
     */
    public function duplicate(ReportDuplicator $duplicator, AuditLogger $audit): mixed
    {
        $this->authorize('manage-reports');

        $copy = $duplicator->duplicate($this->report, auth()->id());
        $audit->log('report.duplicated', $copy, metadata: ['from' => $this->report->id]);

        return $this->redirectRoute('reports.edit', $copy, navigate: true);
    }

    /**
     * Update the schedule that drives this report's site — the frequency,
     * template and auto-send apply to every future report for the site.
     */
    public function saveSchedule(AuditLogger $audit): void
    {
        $this->authorize('manage-sites');

        $validated = $this->validate([
            'report_frequency' => ['required', 'in:none,weekly,monthly,quarterly'],
            'report_template_id' => ['nullable', 'integer', 'exists:report_templates,id'],
            'auto_send' => ['in:,yes,no'],
            'report_generation_delay_days' => ['integer', 'min:0', 'max:28'],
        ]);

        $autoSend = match ($validated['auto_send']) {
            'yes' => true, 'no' => false, default => null
        };

        // Nothing to template when the site isn't on a schedule.
        if ($validated['report_frequency'] === 'none') {
            $validated['report_template_id'] = null;
        }

        $this->report->site->update([
            'report_frequency' => $validated['report_frequency'],
            'report_template_id' => $validated['report_template_id'],
            'auto_send' => $autoSend,
            'report_generation_delay_days' => $validated['report_generation_delay_days'],
        ]);
        $audit->log('site.updated', $this->report->site);

        $this->report_template_id = $validated['report_template_id'];

        $this->dispatch('toast', message: 'Schedule updated.', type: 'ok');
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

    /**
     * @return array<string, string>
     */
    public function frequencies(): array
    {
        return ReportFrequency::options();
    }

    /**
     * @return Collection<int, ReportTemplate>
     */
    public function templates(): Collection
    {
        return ReportTemplate::query()->orderBy('name')->get(['id', 'name']);
    }

    public function render(): mixed
    {
        return view('livewire.reports.show', [
            'deliveries' => $this->report->deliveries()->with('creator')->latest()->get(),
        ]);
    }
}
