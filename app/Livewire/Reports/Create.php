<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\ReportTemplate;
use App\Models\Site;
use App\Reporting\ReportComposer;
use App\Support\AuditLogger;
use App\Support\DateRange;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('New report')]
class Create extends Component
{
    public ?int $site_id = null;

    public string $title = '';

    public ?int $report_template_id = null;

    public string $preset = 'last_month';

    public string $range_start = '';

    public string $range_end = '';

    public bool $compare_previous = true;

    public function mount(): void
    {
        $this->authorize('manage-reports');

        $this->site_id = (int) request()->integer('site') ?: null;
        $range = DateRange::fromPreset($this->preset);
        $this->range_start = $range->start->toDateString();
        $this->range_end = $range->end->toDateString();
    }

    public function updatedPreset(string $value): void
    {
        if ($value === 'custom') {
            return;
        }

        $range = DateRange::fromPreset($value);
        $this->range_start = $range->start->toDateString();
        $this->range_end = $range->end->toDateString();
    }

    public function save(AuditLogger $audit): mixed
    {
        $this->authorize('manage-reports');

        $validated = $this->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'title' => ['required', 'string', 'max:255'],
            'report_template_id' => ['nullable', 'integer', 'exists:report_templates,id'],
            'range_start' => ['required', 'date'],
            'range_end' => ['required', 'date', 'after_or_equal:range_start'],
            'compare_previous' => ['boolean'],
        ]);

        $template = $validated['report_template_id'] !== null
            ? ReportTemplate::query()->whereKey($validated['report_template_id'])->first()
            : null;

        $report = app(ReportComposer::class)->compose(
            site: Site::query()->findOrFail($validated['site_id']),
            range: new DateRange($validated['range_start'], $validated['range_end']),
            title: $validated['title'],
            template: $template,
            comparePrevious: $validated['compare_previous'] ?? true,
            createdBy: auth()->id(),
        );

        $audit->log('report.created', $report);

        return $this->redirectRoute('reports.edit', $report, navigate: true);
    }

    /**
     * @return Collection<int, Site>
     */
    public function sites(): Collection
    {
        return Site::query()->with('client')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, ReportTemplate>
     */
    public function templates(): Collection
    {
        return ReportTemplate::query()->orderBy('name')->get();
    }

    public function render(): mixed
    {
        return view('livewire.reports.create');
    }
}
