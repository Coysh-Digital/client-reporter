<?php

declare(strict_types=1);

namespace App\Livewire\Templates;

use App\Models\ReportTemplate;
use App\Reporting\BlockTypeRegistry;
use App\Support\AuditLogger;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Report template')]
class Form extends Component
{
    public ?ReportTemplate $template = null;

    public string $name = '';

    public string $description = '';

    /** @var array<int, array{type: string, heading: string, config: array<string, mixed>}> */
    public array $blocks = [];

    public function mount(?ReportTemplate $template = null): void
    {
        $this->authorize('manage-reports');

        if ($template !== null && $template->exists) {
            $this->template = $template;
            $this->name = $template->name;
            $this->description = (string) $template->description;
            $this->blocks = array_map(fn (array $b): array => [
                'type' => $b['type'],
                'heading' => $b['heading'] ?? '',
                'config' => $b['config'] ?? [],
            ], $template->blocks);
        }
    }

    public function addBlock(string $type): void
    {
        $blockType = app(BlockTypeRegistry::class)->find($type);
        if ($blockType === null) {
            return;
        }

        $this->blocks[] = [
            'type' => $type,
            'heading' => $blockType->label(),
            'config' => $blockType->defaultConfig(),
        ];
    }

    public function removeBlock(int $index): void
    {
        unset($this->blocks[$index]);
        $this->blocks = array_values($this->blocks);
    }

    /**
     * @param  array<int, int>  $order
     */
    public function reorder(array $order): void
    {
        $reordered = [];
        foreach ($order as $index) {
            if (isset($this->blocks[$index])) {
                $reordered[] = $this->blocks[$index];
            }
        }
        $this->blocks = $reordered;
    }

    public function save(AuditLogger $audit): mixed
    {
        $this->authorize('manage-reports');

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $registry = app(BlockTypeRegistry::class);
        $clean = [];
        foreach ($this->blocks as $block) {
            $blockType = $registry->find($block['type']);
            if ($blockType === null) {
                continue;
            }
            $clean[] = [
                'type' => $block['type'],
                'heading' => $block['heading'] !== '' ? $block['heading'] : $blockType->label(),
                'config' => $blockType->normaliseConfig($block['config']),
            ];
        }

        $data = ['name' => $this->name, 'description' => $this->description ?: null, 'blocks' => $clean];

        if ($this->template !== null) {
            $this->template->update($data);
            $audit->log('report_template.updated', $this->template);
        } else {
            $this->template = ReportTemplate::query()->create($data);
            $audit->log('report_template.created', $this->template);
        }

        session()->flash('status', 'Template saved.');

        return $this->redirectRoute('templates.index', navigate: true);
    }

    public function render(): mixed
    {
        $registry = app(BlockTypeRegistry::class);

        return view('livewire.templates.form', [
            'grouped' => $registry->grouped(),
            'registry' => $registry,
        ]);
    }
}
