<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\Report;
use App\Models\ReportTemplate;
use App\Models\Site;
use App\Reporting\BlockAvailability;
use App\Reporting\BlockTypeRegistry;
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

    /**
     * The default sections used when no template is chosen.
     *
     * @var array<int, array{type: string, heading: string}>
     */
    private array $blankBlocks = [
        ['type' => 'cover', 'heading' => 'Cover'],
        ['type' => 'text', 'heading' => 'Introduction'],
        ['type' => 'website-overview', 'heading' => 'Website overview'],
        ['type' => 'closing', 'heading' => 'Thank you'],
    ];

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

        $report = Report::query()->create([
            'site_id' => $validated['site_id'],
            'title' => $validated['title'],
            'report_template_id' => $validated['report_template_id'] ?? null,
            'range_start' => $validated['range_start'],
            'range_end' => $validated['range_end'],
            'compare_previous' => $validated['compare_previous'] ?? true,
            'created_by' => auth()->id(),
            'status' => 'draft',
        ]);

        $this->seedBlocks($report);
        $audit->log('report.created', $report);

        return $this->redirectRoute('reports.edit', $report, navigate: true);
    }

    private function seedBlocks(Report $report): void
    {
        $definitions = $this->blankBlocks;

        if ($this->report_template_id !== null) {
            $template = ReportTemplate::query()->whereKey($this->report_template_id)->first();

            if ($template !== null && $template->blocks !== []) {
                $definitions = $template->blocks;
            }
        }

        $registry = app(BlockTypeRegistry::class);
        $availability = app(BlockAvailability::class);
        $connectedKeys = $availability->connectedKeys($report->site);

        $position = 0;
        foreach (array_values($definitions) as $definition) {
            $blockType = $registry->find($definition['type']);
            // Only seed blocks the registry knows AND the site can actually feed.
            if ($blockType === null || ! $availability->isAvailable($blockType, $report->site, $connectedKeys)) {
                continue;
            }

            $report->blocks()->create([
                'type' => $definition['type'],
                'position' => $position++,
                'heading' => $definition['heading'] ?? $blockType->label(),
                'config' => $definition['config'] ?? ($blockType->defaultConfig() ?: null),
            ]);
        }
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
