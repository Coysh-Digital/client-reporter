<div>
    <x-breadcrumbs :items="[['label' => 'Reports', 'href' => route('reports.index')], ['label' => $report->site->name, 'href' => route('sites.show', $report->site)], ['label' => $title ?: 'Untitled report']]" />

    @if ($report->generationFailed())
        <x-alert variant="danger" class="mb-4" title="Generation failed">
            {{ $report->generation_error }}
            <x-slot:action><x-button size="sm" wire:click="retryGeneration" icon="arrow-path">Try again</x-button></x-slot:action>
        </x-alert>
    @endif

    <x-page-header :title="$title ?: 'Untitled report'" subtitle="Build and arrange the sections your client will see.">
        <x-slot:actions>
            <x-button :href="route('reports.show', $report)" icon="arrow-up-right-from-square">Open report</x-button>
            <x-button variant="primary" wire:click="generate" :disabled="$report->isGenerating()">
                <span wire:loading.remove wire:target="generate" class="flex items-center gap-2">
                    <x-icon name="file-chart-column" class="h-3.5 w-3.5" />
                    Generate &amp; view
                </span>
                <span wire:loading wire:target="generate">Queuing…</span>
            </x-button>
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
                <div class="cr-panel-header">
                    <h2 class="cr-eyebrow">Report settings</h2>
                    <span class="text-xs text-faint" wire:loading wire:target="saveSettings, persistBlock">Saving…</span>
                    <span class="text-xs text-faint" wire:loading.remove wire:target="saveSettings, persistBlock">Saved automatically</span>
                </div>
                <div class="space-y-4 px-5 py-4">
                    <x-field label="Title" for="report-title" name="title">
                        <input wire:model="title" id="report-title" wire:blur="saveSettings" class="cr-input">
                    </x-field>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-field label="Period" for="report-preset" name="preset">
                            <select wire:model.live="preset" id="report-preset" class="cr-input">
                                @foreach (\App\Support\DateRange::presets() as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-field>
                        <x-field label="From" for="report-range-start" name="range_start">
                            <input type="date" wire:model="range_start" id="report-range-start" wire:change="saveSettings" class="cr-input">
                        </x-field>
                        <x-field label="To" for="report-range-end" name="range_end">
                            <input type="date" wire:model="range_end" id="report-range-end" wire:change="saveSettings" class="cr-input">
                        </x-field>
                    </div>
                    <x-checkbox wire:model="compare_previous" wire:change="saveSettings" id="report-compare" label="Compare with the previous period" />
                </div>
            </div>

            {{-- Blocks --}}
            <div class="cr-panel">
                <div class="cr-panel-header">
                    <h2 class="cr-eyebrow">Sections</h2>
                    @include('livewire.reports.partials.add-section-menu')
                </div>

                <div class="px-4 py-4">
                    @if ($aiError)
                        <x-alert variant="danger" class="mb-3">{{ $aiError }}</x-alert>
                    @endif

                    @if ($blocks->isEmpty())
                        {{-- Quick-start empty state --}}
                        <div class="rounded-xl border border-dashed border-line-strong px-5 py-8 text-center">
                            <p class="text-sm font-medium text-ink">Start building your report</p>
                            <p class="mt-1 text-xs text-muted">Add a section to begin, or apply one of your templates.</p>

                            @if ($quickStart->isNotEmpty())
                                <div class="mt-4 flex flex-wrap justify-center gap-2">
                                    @foreach ($quickStart as $type)
                                        <x-button wire:click="addBlock('{{ $type->type() }}')">
                                            <span class="inline-block h-4 w-4 align-middle">{!! \App\Support\ReportIcons::html($type->icon(), '#8a6a2c') !!}</span>
                                            {{ $type->label() }}
                                        </x-button>
                                    @endforeach
                                </div>
                            @endif

                            @if ($templates->isNotEmpty())
                                <div class="mt-5 border-t border-line pt-4">
                                    <p class="cr-eyebrow mb-2">Apply a template</p>
                                    <div class="flex flex-wrap justify-center gap-2">
                                        @foreach ($templates as $template)
                                            <x-button wire:click="applyTemplate({{ $template->id }})" icon="layer-group">{{ $template->name }}</x-button>
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
                                        @php $blockLabel = ($edits[$block->id]['heading'] ?? '') !== '' ? $edits[$block->id]['heading'] : ($type?->label() ?? $block->type); @endphp
                                        <button type="button" class="drag-handle cursor-grab text-faint hover:text-muted" aria-label="Drag to reorder {{ $blockLabel }}" title="Drag to reorder">
                                            <x-icon name="grip-dots-vertical" class="h-3.5 w-3.5" />
                                        </button>
                                        <span class="inline-block h-4 w-4 shrink-0 align-middle" aria-hidden="true">{!! \App\Support\ReportIcons::html($type?->icon() ?? 'document', '#8a6a2c') !!}</span>
                                        <button type="button" @click="open = !open" x-bind:aria-expanded="open ? 'true' : 'false'" class="min-w-0 flex-1 truncate text-left text-sm font-medium text-ink">
                                            {{ ($edits[$block->id]['heading'] ?? '') !== '' ? $edits[$block->id]['heading'] : ($type?->label() ?? $block->type) }}
                                        </button>
                                        @if ($missing)
                                            <span class="hidden shrink-0 items-center gap-1 text-xs text-warn sm:inline-flex" title="Needs {{ $missing }} connected">
                                                <span class="inline-flex h-1.5 w-1.5 rounded-full" style="background:var(--color-warn);"></span>needs {{ $missing }}
                                            </span>
                                        @endif
                                        @if ($hidden)<span class="shrink-0 text-2xs uppercase tracking-wide text-faint">Hidden</span>@endif

                                        {{-- Action bar --}}
                                        <div class="flex shrink-0 items-center">
                                            <x-icon-button icon="chevron-up" wire:click="moveBlock({{ $block->id }}, 'up')" :disabled="$loop->first" label="Move {{ $blockLabel }} up" />
                                            <x-icon-button icon="chevron-down" wire:click="moveBlock({{ $block->id }}, 'down')" :disabled="$loop->last" label="Move {{ $blockLabel }} down" />
                                            <x-dropdown>
                                                <x-slot:trigger>
                                                    <button type="button" class="cr-btn-icon" aria-label="More actions for {{ $blockLabel }}">
                                                        <x-icon name="ellipsis-horizontal" class="h-4 w-4" />
                                                    </button>
                                                </x-slot:trigger>
                                                <x-dropdown-item wire:click="duplicateBlock({{ $block->id }})" icon="document-duplicate">Duplicate</x-dropdown-item>
                                                <x-dropdown-item wire:click="toggleHidden({{ $block->id }})" :icon="$hidden ? 'eye' : 'eye-slash'">{{ $hidden ? 'Show in report' : 'Hide from report' }}</x-dropdown-item>
                                                <div class="cr-menu-separator"></div>
                                                <x-confirm-button role="menuitem" class="cr-menu-item cr-menu-item-danger"
                                                    action="removeBlock({{ $block->id }})"
                                                    title="Remove this section?"
                                                    message="“{{ $blockLabel }}” and any commentary you wrote for it are removed from this report."
                                                    confirm="Remove section" :danger="true">
                                                    <x-icon name="trash-can" class="h-3.5 w-3.5 shrink-0" /> Remove
                                                </x-confirm-button>
                                            </x-dropdown>
                                            <button type="button" @click="open = !open" x-bind:aria-expanded="open ? 'true' : 'false'" class="cr-btn-icon" aria-label="Edit {{ $blockLabel }}" title="Edit">
                                                <x-icon name="chevron-down" class="h-3.5 w-3.5 transition-transform" x-bind:class="open && 'rotate-180'" />
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Expandable editor --}}
                                    <div x-show="open" x-cloak class="space-y-3 border-t border-line px-3 py-3">
                                        <x-field label="Heading" :for="'block-'.$block->id.'-heading'" :name="'edits.'.$block->id.'.heading'">
                                            <input wire:model="edits.{{ $block->id }}.heading" id="block-{{ $block->id }}-heading" wire:blur="persistBlock({{ $block->id }})"
                                                   placeholder="{{ $type?->label() ?? 'Section heading' }}" class="cr-input text-sm">
                                        </x-field>

                                        @if (! $type || $type->supportsCommentary())
                                            <x-field label="Commentary" :for="'block-'.$block->id.'-commentary'" :name="'edits.'.$block->id.'.commentary'" optional
                                                     help="Merge tags fill in automatically: @{{ client }}, @{{ contact }}, @{{ site }}, @{{ period }}, @{{ agency }}.">
                                                <textarea wire:model="edits.{{ $block->id }}.commentary" id="block-{{ $block->id }}-commentary" wire:blur="persistBlock({{ $block->id }})"
                                                          rows="2" placeholder="A note shown under this section — e.g. Hi @{{ client }}," class="cr-input text-sm"></textarea>
                                            </x-field>
                                        @endif

                                        @if ($type && $type->options() !== [])
                                            <div class="space-y-3 rounded-lg border border-line bg-surface p-3">
                                                <p class="cr-eyebrow">Options</p>
                                                @foreach ($type->options() as $opt)
                                                    <div wire:key="opt-{{ $block->id }}-{{ $opt->key }}">
                                                        @php $optId = "opt-{$block->id}-{$opt->key}"; @endphp
                                                        @if ($opt->type === 'toggle')
                                                            <x-checkbox wire:model="edits.{{ $block->id }}.config.{{ $opt->key }}" wire:change="persistBlock({{ $block->id }})" :id="$optId" :label="$opt->label" />
                                                        @elseif ($opt->type === 'number')
                                                            <label for="{{ $optId }}" class="block text-xs font-medium text-muted">{{ $opt->label }}</label>
                                                            <input type="number" id="{{ $optId }}" min="{{ $opt->min }}" max="{{ $opt->max }}"
                                                                   wire:model="edits.{{ $block->id }}.config.{{ $opt->key }}" wire:change="persistBlock({{ $block->id }})"
                                                                   class="cr-input mt-1 text-sm">
                                                        @elseif ($opt->type === 'select')
                                                            <label for="{{ $optId }}" class="block text-xs font-medium text-muted">{{ $opt->label }}</label>
                                                            <select id="{{ $optId }}" wire:model="edits.{{ $block->id }}.config.{{ $opt->key }}" wire:change="persistBlock({{ $block->id }})"
                                                                    class="cr-input mt-1 text-sm">
                                                                @foreach ($opt->choices as $value => $choiceLabel)
                                                                    <option value="{{ $value }}">{{ $choiceLabel }}</option>
                                                                @endforeach
                                                            </select>
                                                        @elseif ($opt->type === 'multiselect')
                                                            <fieldset>
                                                                <legend class="block text-xs font-medium text-muted">{{ $opt->label }}</legend>
                                                                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                                                                    @foreach ($opt->choices as $value => $choiceLabel)
                                                                        <x-checkbox value="{{ $value }}" wire:model="edits.{{ $block->id }}.config.{{ $opt->key }}" wire:change="persistBlock({{ $block->id }})" :label="$choiceLabel" />
                                                                    @endforeach
                                                                </div>
                                                            </fieldset>
                                                        @endif
                                                        @if ($opt->help)<p class="cr-help">{{ $opt->help }}</p>@endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if ($showAi)
                                            <div class="rounded-lg border border-line bg-surface p-3" wire:key="ai-{{ $block->id }}">
                                                <div class="flex items-center justify-between">
                                                    <span class="cr-eyebrow">AI summary</span>
                                                    <button type="button" wire:click="generateAi({{ $block->id }})" class="cr-link text-xs">
                                                        <span wire:loading.remove wire:target="generateAi({{ $block->id }})">{{ ($edits[$block->id]['ai_summary'] ?? '') !== '' ? 'Regenerate' : 'Generate' }}</span>
                                                        <span wire:loading wire:target="generateAi({{ $block->id }})">Generating…</span>
                                                    </button>
                                                </div>
                                                <textarea wire:model="edits.{{ $block->id }}.ai_summary" wire:blur="persistBlock({{ $block->id }})"
                                                          rows="3" placeholder="Written when you generate the report — or click Generate to preview and edit it now."
                                                          aria-label="AI summary for {{ $blockLabel }}" class="cr-input mt-2 text-sm"></textarea>
                                                <p class="mt-1 text-2xs text-faint">
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
            <div class="sticky top-[76px]"
                 x-data="{
                    v: 0, focus: '', loading: true, scrollTop: 0, timer: null,
                    refresh(blockId) {
                        // Debounce bursts of saves; remember where the client was reading.
                        clearTimeout(this.timer);
                        this.timer = setTimeout(() => {
                            try { this.scrollTop = this.$refs.frame.contentWindow.scrollY || 0; } catch (e) {}
                            this.focus = blockId || '';
                            this.loading = true;
                            this.v++;
                        }, 250);
                    },
                    loaded() {
                        this.loading = false;
                        if (!this.focus && this.scrollTop) {
                            try { this.$refs.frame.contentWindow.scrollTo(0, this.scrollTop); } catch (e) {}
                        }
                    }
                 }"
                 x-on:preview-refresh.window="refresh($event.detail && $event.detail.blockId)">
                <div class="mb-2 flex items-center justify-between">
                    <p class="cr-eyebrow">Live preview</p>
                    <x-button size="sm" variant="ghost" icon="arrow-path" x-on:click="refresh()">Refresh</x-button>
                </div>
                <div class="relative overflow-hidden rounded-xl border border-line bg-white" style="height: calc(100vh - 170px); min-height: 520px;">
                    <div x-show="loading" x-cloak x-transition.opacity class="absolute inset-0 z-10 flex items-center justify-center bg-white/70" aria-live="polite">
                        <span class="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3 py-1.5 text-xs font-medium text-muted shadow-sm">
                            <span class="inline-block h-2 w-2 animate-pulse rounded-full" style="background:var(--color-accent);" aria-hidden="true"></span>
                            Refreshing preview…
                        </span>
                    </div>
                    <iframe x-ref="frame" x-bind:src="'{{ route('reports.preview', $report) }}?v=' + v + (focus ? '#block-' + focus : '')"
                            x-on:load="loaded()" title="Live preview of {{ $title ?: 'this report' }}" class="h-full w-full" style="border: 0;"></iframe>
                </div>
            </div>
        </div>
    </div>

    {{-- Generating overlay: a friendlier wait while the background job collects
         fresh data, resolves every section and freezes the render. Shown while
         the report's generation is queued or running; the page polls and moves
         on to the finished report. The cycling messages are cosmetic. --}}
    @if ($report->isGenerating())
    <div wire:poll.2s="pollGeneration" wire:key="generating-overlay"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background:color-mix(in srgb, var(--color-ink) 45%, transparent);backdrop-filter:blur(2px);">
        <div class="w-full max-w-sm rounded-2xl border border-line bg-surface p-6 text-center shadow-xl"
             x-data="{ i: 0, timer: null, messages: [
                'Collecting the latest data…',
                'Crunching the numbers…',
                'Rendering charts…',
                'Writing summaries…',
                'Putting it all together…',
             ] }"
             x-init="timer = setInterval(() => { i = (i + 1) % messages.length }, 1800)"
             x-on:destroy="clearInterval(timer)">
            <div class="mx-auto mb-4 flex h-11 w-11 items-center justify-center rounded-full" style="background:var(--color-accent-soft);">
                <x-icon name="file-chart-column" class="h-5 w-5" style="color:var(--color-accent);" />
            </div>
            <p class="text-md font-semibold text-ink">Generating your report</p>
            <p class="mt-1 h-4 text-sm text-muted" x-text="messages[i]"></p>
            <div class="cr-progress mt-4"><div class="cr-progress-bar"></div></div>
            <p class="mt-3 text-2xs text-faint">
                {{ $report->generation_status === \App\Enums\GenerationStatus::Queued
                    ? 'Waiting for the background worker to pick this up…'
                    : 'This can take a moment while we gather fresh data.' }}
            </p>
        </div>
    </div>
    @endif
</div>
