<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Enums\ConnectionStatus;
use App\Integrations\IntegrationRegistry;
use App\Models\Report;
use App\Reporting\BlockAvailability;
use App\Reporting\BlockTypeRegistry;
use App\Reporting\Contracts\BlockType;
use App\Reporting\ReportGenerator;
use App\Support\AuditLogger;
use App\Support\DateRange;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Builder extends Component
{
    public Report $report;

    // Report settings
    public string $title = '';

    public string $preset = 'last_month';

    public string $range_start = '';

    public string $range_end = '';

    public bool $compare_previous = true;

    public string $intro = '';

    /** @var array<int, array{heading: string, commentary: string, is_hidden: bool, config: array<string, mixed>}> */
    public array $edits = [];

    public function mount(Report $report): void
    {
        $this->authorize('manage-reports');

        $this->report = $report->load('blocks');
        $this->title = $report->title;
        $this->range_start = $report->range_start->toDateString();
        $this->range_end = $report->range_end->toDateString();
        $this->compare_previous = $report->compare_previous;
        $this->intro = (string) $report->intro;
        $this->preset = 'custom';

        foreach ($this->report->blocks as $block) {
            $this->edits[$block->id] = [
                'heading' => (string) $block->heading,
                'commentary' => (string) $block->commentary,
                'is_hidden' => $block->is_hidden,
                'config' => $block->config ?? [],
            ];
        }
    }

    public function updatedPreset(string $value): void
    {
        if ($value === 'custom') {
            return;
        }

        $range = DateRange::fromPreset($value);
        $this->range_start = $range->start->toDateString();
        $this->range_end = $range->end->toDateString();
        $this->saveSettings();
    }

    public function saveSettings(): void
    {
        $this->authorize('manage-reports');

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'range_start' => ['required', 'date'],
            'range_end' => ['required', 'date', 'after_or_equal:range_start'],
            'compare_previous' => ['boolean'],
            'intro' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->report->update([
            'title' => $validated['title'],
            'range_start' => Carbon::parse($validated['range_start']),
            'range_end' => Carbon::parse($validated['range_end']),
            'compare_previous' => $validated['compare_previous'],
            'intro' => $validated['intro'] ?: null,
        ]);

        $this->dispatch('preview-refresh');
    }

    public function addBlock(string $type): void
    {
        $this->authorize('manage-reports');

        $blockType = app(BlockTypeRegistry::class)->find($type);
        if ($blockType === null) {
            return;
        }

        $config = $blockType->defaultConfig();

        $block = $this->report->blocks()->create([
            'type' => $type,
            'position' => (int) $this->report->blocks()->max('position') + 1,
            'heading' => $blockType->label(),
            'config' => $config ?: null,
        ]);

        $this->report->load('blocks');
        $this->edits[$block->id] = ['heading' => (string) $block->heading, 'commentary' => '', 'is_hidden' => false, 'config' => $config];
        $this->dispatch('preview-refresh');
    }

    public function persistBlock(int $blockId): void
    {
        $this->authorize('manage-reports');

        $block = $this->report->blocks()->find($blockId);
        if ($block === null || ! isset($this->edits[$blockId])) {
            return;
        }

        $edit = $this->edits[$blockId];
        $type = app(BlockTypeRegistry::class)->find($block->type);

        $block->update([
            'heading' => $edit['heading'] !== '' ? $edit['heading'] : null,
            'commentary' => $edit['commentary'] !== '' ? $edit['commentary'] : null,
            'is_hidden' => (bool) $edit['is_hidden'],
            'config' => $type !== null ? ($type->normaliseConfig($edit['config']) ?: null) : ($block->config),
        ]);

        $this->dispatch('preview-refresh');
    }

    public function removeBlock(int $blockId): void
    {
        $this->authorize('manage-reports');

        $this->report->blocks()->whereKey($blockId)->delete();
        unset($this->edits[$blockId]);
        $this->report->load('blocks');
        $this->dispatch('preview-refresh');
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        $this->authorize('manage-reports');

        foreach ($orderedIds as $position => $id) {
            $this->report->blocks()->whereKey($id)->update(['position' => $position]);
        }

        $this->report->load('blocks');
        $this->dispatch('preview-refresh');
    }

    public function generate(ReportGenerator $generator, AuditLogger $audit): mixed
    {
        $this->authorize('manage-reports');
        $this->saveSettings();

        $generator->generate($this->report->fresh(['blocks']));
        $audit->log('report.generated', $this->report);

        session()->flash('status', 'Report generated.');

        return $this->redirectRoute('reports.show', $this->report, navigate: true);
    }

    /**
     * The unmet requirement for a block on this site, or null if satisfied.
     *
     * @param  array<int, string>  $connectedKeys
     */
    public function requirementWarning(BlockType $type, array $connectedKeys): ?string
    {
        if ($key = $type->requiresIntegration()) {
            return in_array($key, $connectedKeys, true) ? null : $key;
        }

        if ($category = $type->requiresCategory()) {
            $keys = app(IntegrationRegistry::class)->keysInCategory($category);

            return array_intersect($keys, $connectedKeys) !== [] ? null : strtolower($category->label());
        }

        return null;
    }

    public function render(): mixed
    {
        $registry = app(BlockTypeRegistry::class);

        // Only integrations the site actually has live (connected or needing
        // attention) count towards availability.
        $connectedKeys = $this->report->site->integrations()
            ->whereIn('status', [ConnectionStatus::Connected->value, ConnectionStatus::NeedsAttention->value])
            ->pluck('integration_key')
            ->all();

        // The add-section menu shows only blocks whose data source is available
        // for this site (e.g. no Craft blocks on a WordPress-only site, and the
        // store block only when the site has an ecommerce source).
        $availability = app(BlockAvailability::class);
        $site = $this->report->site;
        $available = [];
        foreach ($registry->grouped() as $group => $types) {
            $usable = array_values(array_filter(
                $types,
                fn (BlockType $type): bool => $availability->isAvailable($type, $site, $connectedKeys),
            ));
            if ($usable !== []) {
                $available[$group] = $usable;
            }
        }

        return view('livewire.reports.builder', [
            'grouped' => $available,
            'blocks' => $this->report->blocks()->orderBy('position')->get(),
            'registry' => $registry,
            'connectedKeys' => $connectedKeys,
        ]);
    }
}
