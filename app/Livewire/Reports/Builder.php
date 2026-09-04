<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Ai\AiSummariser;
use App\Enums\ConnectionStatus;
use App\Integrations\IntegrationRegistry;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Reporting\BlockAvailability;
use App\Reporting\Blocks\Ai\AiSummaryBlock;
use App\Reporting\BlockTypeRegistry;
use App\Reporting\Contracts\BlockType;
use App\Reporting\ReportGenerator;
use App\Support\AuditLogger;
use App\Support\DateRange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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

    /** @var array<int, array{heading: string, commentary: string, ai_summary: string, is_hidden: bool, config: array<string, mixed>}> */
    public array $edits = [];

    /** Transient notice shown when an on-demand AI summary could not be produced. */
    public string $aiError = '';

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
                'ai_summary' => (string) $block->ai_summary,
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
        $this->edits[$block->id] = ['heading' => (string) $block->heading, 'commentary' => '', 'ai_summary' => '', 'is_hidden' => false, 'config' => $config];
        $this->dispatch('preview-refresh', blockId: $block->id);
    }

    /**
     * Seed an empty report from a template's sections (skipping any the site
     * can't feed). Only offered from the empty state.
     */
    public function applyTemplate(int $templateId): void
    {
        $this->authorize('manage-reports');

        if ($this->report->blocks()->exists()) {
            return;
        }

        $template = ReportTemplate::query()->find($templateId);
        if ($template === null) {
            return;
        }

        $registry = app(BlockTypeRegistry::class);
        $availability = app(BlockAvailability::class);
        $connectedKeys = $availability->connectedKeys($this->report->site);

        $position = 0;
        foreach ($template->blocks as $definition) {
            $blockType = $registry->find($definition['type'] ?? '');
            if ($blockType === null || ! $availability->isAvailable($blockType, $this->report->site, $connectedKeys)) {
                continue;
            }

            $this->report->blocks()->create([
                'type' => $blockType->type(),
                'position' => $position++,
                'heading' => $definition['heading'] ?? $blockType->label(),
                'config' => $definition['config'] ?? ($blockType->defaultConfig() ?: null),
            ]);
        }

        $this->report->load('blocks');
        foreach ($this->report->blocks as $block) {
            $this->edits[$block->id] = [
                'heading' => (string) $block->heading,
                'commentary' => (string) $block->commentary,
                'ai_summary' => (string) $block->ai_summary,
                'is_hidden' => $block->is_hidden,
                'config' => $block->config ?? [],
            ];
        }
        $this->dispatch('preview-refresh');
    }

    /**
     * Clone a section (type, config, heading, commentary) directly beneath it.
     */
    public function duplicateBlock(int $blockId): void
    {
        $this->authorize('manage-reports');

        $block = $this->report->blocks()->find($blockId);
        if ($block === null) {
            return;
        }

        // Make room immediately after the original, then insert the copy there.
        $this->report->blocks()->where('position', '>', $block->position)->increment('position');

        $clone = $this->report->blocks()->create([
            'type' => $block->type,
            'position' => $block->position + 1,
            'heading' => $block->heading,
            'commentary' => $block->commentary,
            'ai_summary' => $block->ai_summary,
            'config' => $block->config,
            'is_hidden' => $block->is_hidden,
        ]);

        $this->report->load('blocks');
        $this->edits[$clone->id] = [
            'heading' => (string) $clone->heading,
            'commentary' => (string) $clone->commentary,
            'ai_summary' => (string) $clone->ai_summary,
            'is_hidden' => $clone->is_hidden,
            'config' => $clone->config ?? [],
        ];
        $this->dispatch('preview-refresh', blockId: $clone->id);
    }

    /**
     * Move a section up or down — a keyboard/touch-friendly alternative to drag.
     */
    public function moveBlock(int $blockId, string $direction): void
    {
        $this->authorize('manage-reports');

        $ordered = $this->report->blocks()->orderBy('position')->get();
        $index = $ordered->search(fn ($b): bool => $b->id === $blockId);
        if ($index === false) {
            return;
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if ($target < 0 || $target >= $ordered->count()) {
            return;
        }

        $a = $ordered[$index];
        $b = $ordered[$target];
        [$a->position, $b->position] = [$b->position, $a->position];
        $a->save();
        $b->save();

        $this->report->load('blocks');
        $this->dispatch('preview-refresh', blockId: $blockId);
    }

    /**
     * Toggle a section's hidden state from the row's action bar.
     */
    public function toggleHidden(int $blockId): void
    {
        $this->authorize('manage-reports');

        $block = $this->report->blocks()->find($blockId);
        if ($block === null || ! isset($this->edits[$blockId])) {
            return;
        }

        $block->update(['is_hidden' => ! $block->is_hidden]);
        $this->edits[$blockId]['is_hidden'] = $block->is_hidden;
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
            'ai_summary' => $edit['ai_summary'] !== '' ? $edit['ai_summary'] : null,
            'is_hidden' => (bool) $edit['is_hidden'],
            'config' => $type !== null ? ($type->normaliseConfig($edit['config']) ?: null) : ($block->config),
        ]);

        $this->dispatch('preview-refresh', blockId: $blockId);
    }

    /**
     * Write (or rewrite) a block's AI summary on demand, so staff can preview
     * and edit it before generating. For the roundup block this summarises the
     * whole report; for a data section it summarises that section.
     */
    public function generateAi(int $blockId, AiSummariser $ai): void
    {
        $this->authorize('manage-reports');

        $this->aiError = '';

        $block = $this->report->blocks()->find($blockId);
        if ($block === null || ! isset($this->edits[$blockId])) {
            return;
        }

        $text = $block->type === AiSummaryBlock::TYPE
            ? $ai->roundup($this->report)
            : $ai->forBlock($this->report, $block);

        if ($text === null) {
            $this->aiError = 'Could not generate a summary. Check the AI settings and that this section has data.';

            return;
        }

        $block->update(['ai_summary' => $text]);
        $this->edits[$blockId]['ai_summary'] = $text;
        $this->dispatch('preview-refresh', blockId: $blockId);
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

    /**
     * A short "needs X" hint for a block that isn't yet available on this site,
     * shown greyed in the add menu so staff know how to unlock it.
     *
     * @param  array<int, string>  $connectedKeys
     */
    public function availabilityHint(BlockType $type, array $connectedKeys): string
    {
        if ($requirement = $this->requirementWarning($type, $connectedKeys)) {
            return 'needs '.Str::of($requirement)->replace('_', ' ')->headline()->lower();
        }

        return 'not available for this site';
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

        // The add-section menu splits into blocks the site can feed now and
        // blocks that need an integration first (shown greyed with a hint).
        $availability = app(BlockAvailability::class);
        $site = $this->report->site;
        $available = [];
        $unavailable = [];
        foreach ($registry->grouped() as $group => $types) {
            foreach ($types as $type) {
                if ($availability->isAvailable($type, $site, $connectedKeys)) {
                    $available[$group][] = $type;
                } else {
                    $unavailable[$group][] = $type;
                }
            }
        }

        return view('livewire.reports.builder', [
            'grouped' => $available,
            'unavailableGrouped' => $unavailable,
            'blocks' => $this->report->blocks()->orderBy('position')->get(),
            'registry' => $registry,
            'connectedKeys' => $connectedKeys,
            'aiEnabled' => app(AiSummariser::class)->enabled(),
            'templates' => ReportTemplate::query()->orderBy('name')->get(),
        ]);
    }
}
