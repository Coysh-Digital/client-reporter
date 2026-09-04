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

    @php
        // Flatten the available blocks into a type => instance map for the
        // quick-start buttons, and pick a few common sections to offer.
        $availableTypes = collect($grouped)->flatten()->keyBy(fn ($t) => $t->type());
        $quickStart = collect(['cover', 'analytics.site_traffic', 'uptime.overview', 'ecommerce.summary', 'search.summary', 'text'])
            ->map(fn ($type) => $availableTypes->get($type))->filter()->take(5);
    @endphp

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
                    @include('livewire.reports.partials.add-section-menu')
                </div>

                <div class="px-4 py-4">
                    @if ($aiError)
                        <div class="mb-3 rounded-lg bg-danger-soft px-3 py-2 text-xs text-danger">{{ $aiError }}</div>
                    @endif

                    @if ($blocks->isEmpty())
                        {{-- Quick-start empty state --}}
                        <div class="rounded-xl border border-dashed border-line-strong px-5 py-8 text-center">
                            <p class="text-sm font-medium text-ink">Start building your report</p>
                            <p class="mt-1 text-xs text-muted">Add a section to begin, or apply one of your templates.</p>

                            @if ($quickStart->isNotEmpty())
                                <div class="mt-4 flex flex-wrap justify-center gap-2">
                                    @foreach ($quickStart as $type)
                                        <button wire:click="addBlock('{{ $type->type() }}')" class="cr-btn cr-btn-secondary text-sm">
                                            <span class="inline-block h-4 w-4 align-middle">{!! \App\Support\ReportIcons::html($type->icon(), '#8a6a2c') !!}</span>
                                            {{ $type->label() }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @if ($templates->isNotEmpty())
                                <div class="mt-5 border-t border-line pt-4">
                                    <p class="cr-eyebrow mb-2">Apply a template</p>
                                    <div class="flex flex-wrap justify-center gap-2">
                                        @foreach ($templates as $template)
                                            <button wire:click="applyTemplate({{ $template->id }})" class="cr-btn cr-btn-secondary text-sm">
                                                <x-icon name="layer-group" class="h-3.5 w-3.5" />
                                                {{ $template->name }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <ul x-data x-init="window.Sortable.create($el, { handle: '.drag-handle', animation: 150, onEnd() {
                                $wire.reorder(Array.from($el.children).map(c => parseInt(c.getAttribute('data-id'))));
                            }})"
                            class="space-y-2">
                            @foreach ($blocks as $block)
                                @php
                                    $type = $registry->find($block->type);
                                    $missing = $type ? $this->requirementWarning($type, $connectedKeys) : null;
                                    $hidden = $edits[$block->id]['is_hidden'] ?? false;
                                    $isRoundup = $block->type === \App\Reporting\Blocks\Ai\AiSummaryBlock::TYPE;
                                    $showAi = $aiEnabled && ($isRoundup || ($type && $type->supportsAiSummary() && ($edits[$block->id]['config']['ai_summary'] ?? false)));
                                @endphp
                                <li data-id="{{ $block->id }}" wire:key="block-{{ $block->id }}"
                                    x-data="{ open: false }"
                                    @class([
                                        'rounded-lg border border-line',
                                        'bg-paper/40' => ! $hidden,
                                        'bg-paper/60 opacity-60' => $hidden,
                                    ])>
                                    {{-- Compact header --}}
                                    <div class="flex items-center gap-2 px-3 py-2">
                                        <button type="button" class="drag-handle cursor-grab text-faint hover:text-muted" title="Drag to reorder">
                                            <x-icon name="grip-dots-vertical" class="h-3.5 w-3.5" />
                                        </button>
                                        <span class="inline-block h-4 w-4 shrink-0 align-middle">{!! \App\Support\ReportIcons::html($type?->icon() ?? 'document', '#8a6a2c') !!}</span>
                                        <button type="button" @click="open = !open" class="min-w-0 flex-1 truncate text-left text-sm font-medium text-ink">
                                            {{ ($edits[$block->id]['heading'] ?? '') !== '' ? $edits[$block->id]['heading'] : ($type?->label() ?? $block->type) }}
                                        </button>
                                        @if ($missing)
                                            <span class="hidden shrink-0 items-center gap-1 text-xs text-warn sm:inline-flex" title="Needs {{ $missing }} connected">
                                                <span class="inline-flex h-1.5 w-1.5 rounded-full" style="background:var(--color-warn);"></span>needs {{ $missing }}
                                            </span>
                                        @endif
                                        @if ($hidden)<span class="shrink-0 text-[11px] uppercase tracking-wide text-faint">Hidden</span>@endif

                                        {{-- Action bar --}}
                                        <div class="flex shrink-0 items-center text-faint">
                                            <button type="button" wire:click="moveBlock({{ $block->id }}, 'up')" @disabled($loop->first) class="rounded p-1 hover:bg-paper hover:text-ink disabled:opacity-30" title="Move up">
                                                <x-icon name="chevron-up" class="h-3.5 w-3.5" />
                                            </button>
                                            <button type="button" wire:click="moveBlock({{ $block->id }}, 'down')" @disabled($loop->last) class="rounded p-1 hover:bg-paper hover:text-ink disabled:opacity-30" title="Move down">
                                                <x-icon name="chevron-down" class="h-3.5 w-3.5" />
                                            </button>
                                            <button type="button" wire:click="duplicateBlock({{ $block->id }})" class="rounded p-1 hover:bg-paper hover:text-ink" title="Duplicate">
                                                <x-icon name="document-duplicate" class="h-3.5 w-3.5" />
                                            </button>
                                            <button type="button" wire:click="toggleHidden({{ $block->id }})" class="rounded p-1 hover:bg-paper hover:text-ink" title="{{ $hidden ? 'Show in report' : 'Hide from report' }}">
                                                <x-icon name="{{ $hidden ? 'eye-slash' : 'eye' }}" class="h-3.5 w-3.5" />
                                            </button>
                                            <button type="button" wire:click="removeBlock({{ $block->id }})" wire:confirm="Remove this section?" class="rounded p-1 text-faint hover:bg-danger-soft hover:text-danger" title="Remove">
                                                <x-icon name="trash-can" class="h-3.5 w-3.5" />
                                            </button>
                                            <button type="button" @click="open = !open" class="rounded p-1 hover:bg-paper hover:text-ink" title="Edit">
                                                <x-icon name="chevron-down" class="h-3.5 w-3.5 transition-transform" x-bind:class="open && 'rotate-180'" />
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Expandable editor --}}
                                    <div x-show="open" x-cloak class="space-y-3 border-t border-line px-3 py-3">
                                        <div>
                                            <label class="cr-label">Heading</label>
                                            <input wire:model="edits.{{ $block->id }}.heading" wire:blur="persistBlock({{ $block->id }})"
                                                   placeholder="{{ $type?->label() ?? 'Section heading' }}" class="cr-input mt-1 text-sm">
                                        </div>

                                        @if (! $type || $type->supportsCommentary())
                                            <div>
                                                <label class="cr-label">Commentary <span class="font-normal text-faint">(optional)</span></label>
                                                <textarea wire:model="edits.{{ $block->id }}.commentary" wire:blur="persistBlock({{ $block->id }})"
                                                          rows="2" placeholder="A note shown under this section" class="cr-input mt-1 text-sm"></textarea>
                                            </div>
                                        @endif

                                        @if ($type && $type->options() !== [])
                                            <div class="space-y-3 rounded-lg border border-line bg-surface p-3">
                                                <p class="cr-eyebrow">Options</p>
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
                                        @endif

                                        @if ($showAi)
                                            <div class="rounded-lg border border-line bg-surface p-3" wire:key="ai-{{ $block->id }}">
                                                <div class="flex items-center justify-between">
                                                    <span class="cr-eyebrow">AI summary</span>
                                                    <button type="button" wire:click="generateAi({{ $block->id }})" class="text-xs font-semibold" style="color:var(--color-accent)">
                                                        <span wire:loading.remove wire:target="generateAi({{ $block->id }})">{{ ($edits[$block->id]['ai_summary'] ?? '') !== '' ? 'Regenerate' : 'Generate' }}</span>
                                                        <span wire:loading wire:target="generateAi({{ $block->id }})">Generating…</span>
                                                    </button>
                                                </div>
                                                <textarea wire:model="edits.{{ $block->id }}.ai_summary" wire:blur="persistBlock({{ $block->id }})"
                                                          rows="3" placeholder="Written when you generate the report — or click Generate to preview and edit it now."
                                                          class="cr-input mt-2 text-sm"></textarea>
                                                <p class="mt-1 text-[11px] text-faint">
                                                    {{ $isRoundup ? 'Summarises the whole report from every section’s figures.' : 'An AI paragraph for this section. You can edit it before generating.' }}
                                                </p>
                                            </div>
                                        @endif
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
            <div class="sticky top-[76px]" x-data="{ v: 0, focus: '' }"
                 x-on:preview-refresh.window="v++; if ($event.detail && $event.detail.blockId) focus = $event.detail.blockId">
                <div class="mb-2 flex items-center justify-between">
                    <p class="cr-eyebrow">Live preview</p>
                    <button @click="v++" class="text-xs text-muted hover:text-ink">Refresh</button>
                </div>
                <div class="overflow-hidden rounded-xl border border-line bg-white" style="height: 620px;">
                    <iframe x-bind:src="'{{ route('reports.preview', $report) }}?v=' + v + (focus ? '#block-' + focus : '')"
                            class="h-full w-full" style="border: 0;"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>
