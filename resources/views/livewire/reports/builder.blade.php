<div>
    <div class="mb-2 flex items-center gap-2 text-sm text-faint">
        <a href="{{ route('reports.index') }}" wire:navigate class="hover:text-ink">Reports</a>
        <span style="color:var(--color-line-strong);">/</span>
        <a href="{{ route('sites.show', $report->site) }}" wire:navigate class="hover:text-ink">{{ $report->site->name }}</a>
    </div>

    <x-page-header :title="$title ?: 'Untitled report'" subtitle="Build and arrange the sections your client will see.">
        <x-slot:actions>
            <a href="{{ route('reports.show', $report) }}" wire:navigate class="cr-btn cr-btn-secondary">
                <x-icon name="arrow-up-right-from-square" class="h-3.5 w-3.5" />
                Preview
            </a>
            <button wire:click="generate" class="cr-btn cr-btn-primary">
                <span wire:loading.remove wire:target="generate" class="flex items-center gap-2">
                    <x-icon name="file-chart-column" class="h-3.5 w-3.5" />
                    Generate &amp; view
                </span>
                <span wire:loading wire:target="generate">Generating…</span>
            </button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-5">
        {{-- Builder --}}
        <div class="space-y-6 lg:col-span-3">
            {{-- Settings --}}
            <div class="cr-panel">
                <div class="border-b border-line px-5 py-3.5">
                    <h2 class="cr-eyebrow">Report settings</h2>
                </div>
                <div class="space-y-4 px-5 py-4">
                    <div>
                        <label class="cr-label">Title</label>
                        <input wire:model="title" wire:blur="saveSettings" class="cr-input">
                        @error('title') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="cr-label">Period</label>
                            <select wire:model.live="preset" class="cr-input">
                                @foreach (\App\Support\DateRange::presets() as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="cr-label">From</label>
                            <input type="date" wire:model="range_start" wire:change="saveSettings" class="cr-input">
                        </div>
                        <div>
                            <label class="cr-label">To</label>
                            <input type="date" wire:model="range_end" wire:change="saveSettings" class="cr-input">
                        </div>
                    </div>
                    @error('range_end') <p class="text-xs text-danger">{{ $message }}</p> @enderror
                    <label class="flex items-center gap-2 text-sm text-muted">
                        <input type="checkbox" wire:model="compare_previous" wire:change="saveSettings" class="rounded border-line-strong text-accent focus:ring-accent">
                        Compare with the previous period
                    </label>
                </div>
            </div>

            {{-- Blocks --}}
            <div class="cr-panel">
                <div class="flex items-center justify-between border-b border-line px-5 py-3">
                    <h2 class="cr-eyebrow">Sections</h2>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" type="button" class="cr-btn cr-btn-secondary text-sm">
                            <x-icon name="plus" class="h-3 w-3" />
                            Add section
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             class="absolute right-0 z-10 mt-1 max-h-80 w-64 overflow-y-auto rounded-lg border border-line bg-surface py-1 shadow-lg">
                            @forelse ($grouped as $group => $types)
                                <p class="px-3 py-1 text-[10.5px] font-bold uppercase tracking-[0.07em] text-faint">{{ $group }}</p>
                                @foreach ($types as $type)
                                    <button type="button" wire:click="addBlock('{{ $type->type() }}')" @click="open = false"
                                            class="block w-full px-3 py-1.5 text-left text-sm hover:bg-paper">
                                        <span class="text-ink">{{ $type->label() }}</span>
                                    </button>
                                @endforeach
                            @empty
                                <p class="px-3 py-3 text-center text-xs text-faint">No sections available. Connect an integration on this site first.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="px-4 py-4">
                    @if ($blocks->isEmpty())
                        <p class="py-6 text-center text-sm text-muted">No sections yet. Add your first section above.</p>
                    @else
                        <ul x-data x-init="window.Sortable.create($el, { handle: '.drag-handle', animation: 150, onEnd() {
                                $wire.reorder(Array.from($el.children).map(c => parseInt(c.getAttribute('data-id'))));
                            }})"
                            class="space-y-2">
                            @foreach ($blocks as $block)
                                @php $type = $registry->find($block->type); $missing = $type ? $this->requirementWarning($type, $connectedKeys) : null; @endphp
                                <li data-id="{{ $block->id }}" wire:key="block-{{ $block->id }}"
                                    @class([
                                        'rounded-lg border px-4 py-3',
                                        'border-line bg-paper/40' => ! ($edits[$block->id]['is_hidden'] ?? false),
                                        'border-line bg-paper/60 opacity-60' => ($edits[$block->id]['is_hidden'] ?? false),
                                    ])>
                                    <div class="flex items-start gap-3">
                                        <button type="button" class="drag-handle mt-0.5 cursor-grab text-faint hover:text-muted" title="Drag to reorder">
                                            <x-icon name="grip-dots-vertical" class="h-3.5 w-3.5" />
                                        </button>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[11px] font-bold uppercase tracking-wide text-faint">{{ $type?->label() ?? $block->type }}</span>
                                                @if ($missing) <span class="inline-flex h-1.5 w-1.5 rounded-full" style="background:var(--color-warn);" title="Needs {{ $missing }} connected"></span><span class="text-xs text-warn">needs {{ $missing }}</span> @endif
                                            </div>
                                            <input wire:model="edits.{{ $block->id }}.heading" wire:blur="persistBlock({{ $block->id }})"
                                                   placeholder="Section heading" class="cr-input mt-2 text-sm">
                                            <textarea wire:model="edits.{{ $block->id }}.commentary" wire:blur="persistBlock({{ $block->id }})"
                                                      rows="2" placeholder="Commentary (optional)" class="cr-input mt-2 text-sm"></textarea>

                                            @if ($type && $type->options() !== [])
                                                <div x-data="{ open: false }" class="mt-2">
                                                    <button type="button" @click="open = !open" class="text-xs font-semibold" style="color:var(--color-accent)">
                                                        <span x-show="!open">Options</span><span x-show="open" x-cloak>Hide options</span>
                                                    </button>
                                                    <div x-show="open" x-cloak class="mt-2 space-y-3 rounded-lg border border-line bg-surface p-3">
                                                        @foreach ($type->options() as $opt)
                                                            <div wire:key="opt-{{ $block->id }}-{{ $opt->key }}">
                                                                @if ($opt->type === 'toggle')
                                                                    <label class="flex items-center gap-2 text-xs text-ink">
                                                                        <input type="checkbox" wire:model="edits.{{ $block->id }}.config.{{ $opt->key }}" wire:change="persistBlock({{ $block->id }})"
                                                                               class="rounded border-line-strong text-accent focus:ring-accent">
                                                                        {{ $opt->label }}
                                                                    </label>
                                                                @elseif ($opt->type === 'number')
                                                                    <label class="block text-xs font-medium text-muted">{{ $opt->label }}</label>
                                                                    <input type="number" min="{{ $opt->min }}" max="{{ $opt->max }}"
                                                                           wire:model="edits.{{ $block->id }}.config.{{ $opt->key }}" wire:change="persistBlock({{ $block->id }})"
                                                                           class="cr-input mt-1 text-sm">
                                                                @elseif ($opt->type === 'select')
                                                                    <label class="block text-xs font-medium text-muted">{{ $opt->label }}</label>
                                                                    <select wire:model="edits.{{ $block->id }}.config.{{ $opt->key }}" wire:change="persistBlock({{ $block->id }})"
                                                                            class="cr-input mt-1 text-sm">
                                                                        @foreach ($opt->choices as $value => $choiceLabel)
                                                                            <option value="{{ $value }}">{{ $choiceLabel }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                @elseif ($opt->type === 'multiselect')
                                                                    <span class="block text-xs font-medium text-muted">{{ $opt->label }}</span>
                                                                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                                                                        @foreach ($opt->choices as $value => $choiceLabel)
                                                                            <label class="flex items-center gap-1.5 text-xs text-ink">
                                                                                <input type="checkbox" value="{{ $value }}" wire:model="edits.{{ $block->id }}.config.{{ $opt->key }}" wire:change="persistBlock({{ $block->id }})"
                                                                                       class="rounded border-line-strong text-accent focus:ring-accent">
                                                                                {{ $choiceLabel }}
                                                                            </label>
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                                @if ($opt->help)<p class="mt-1 text-[11px] text-faint">{{ $opt->help }}</p>@endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex shrink-0 flex-col items-end gap-2">
                                            <label class="flex items-center gap-1 text-xs text-muted">
                                                <input type="checkbox" wire:model="edits.{{ $block->id }}.is_hidden" wire:change="persistBlock({{ $block->id }})"
                                                       class="rounded border-line-strong text-accent focus:ring-accent">
                                                Hide
                                            </label>
                                            <button wire:click="removeBlock({{ $block->id }})" wire:confirm="Remove this section?"
                                                    class="flex items-center gap-1 text-xs text-danger hover:underline">
                                                <x-icon name="trash-can" class="h-3 w-3" />
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        {{-- Live preview --}}
        <div class="lg:col-span-2">
            <div class="sticky top-[76px]" x-data="{ v: 0 }" x-on:preview-refresh.window="v++">
                <div class="mb-2 flex items-center justify-between">
                    <p class="cr-eyebrow">Live preview</p>
                    <button @click="v++" class="text-xs text-muted hover:text-ink">Refresh</button>
                </div>
                <div class="overflow-hidden rounded-xl border border-line bg-white" style="height: 620px;">
                    <iframe x-bind:src="'{{ route('reports.preview', $report) }}?v=' + v"
                            class="h-full w-full" style="border: 0;"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>
