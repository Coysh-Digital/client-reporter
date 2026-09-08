<div>
    <x-breadcrumbs :items="[['label' => 'Templates', 'href' => route('templates.index')], ['label' => $template?->name ?? 'New template']]" />

    <x-page-header :title="$template ? 'Edit template' : 'New template'" subtitle="Arrange the sections this template adds to a report.">
        <x-slot:actions>
            <x-button :href="route('templates.index')">Cancel</x-button>
            <x-button variant="primary" wire:click="save">
                <span wire:loading.remove wire:target="save">Save template</span>
                <span wire:loading wire:target="save">Saving…</span>
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mx-auto max-w-3xl space-y-6">
        {{-- Details --}}
        <div class="cr-panel">
            <div class="cr-panel-header"><h2 class="cr-eyebrow">Template details</h2></div>
            <div class="space-y-4 px-5 py-5">
                <x-field label="Name" for="name" required>
                    <input wire:model="name" id="name" class="cr-input" placeholder="e.g. Standard monthly care report">
                </x-field>
                <x-field label="Description" for="description" optional>
                    <input wire:model="description" id="description" class="cr-input" placeholder="What this template is for">
                </x-field>
            </div>
        </div>

        {{-- Sections --}}
        <div class="cr-panel">
            <div class="cr-panel-header">
                <h2 class="cr-eyebrow">Sections</h2>
                <x-dropdown width="w-80">
                    <x-slot:trigger>
                        <x-button icon="plus">Add section</x-button>
                    </x-slot:trigger>
                    <div class="max-h-[28rem] overflow-y-auto">
                        @foreach ($grouped as $group => $types)
                            <p class="cr-menu-heading">{{ $group }}</p>
                            @foreach ($types as $type)
                                <button type="button" role="menuitem" wire:click="addBlock('{{ $type->type() }}')" x-on:click="close()"
                                        class="cr-menu-item items-start">
                                    <span class="mt-0.5 inline-block h-4 w-4 shrink-0" aria-hidden="true">{!! \App\Support\ReportIcons::html($type->icon(), '#8a6a2c') !!}</span>
                                    <span class="min-w-0">
                                        <span class="block text-sm text-ink">{{ $type->label() }}</span>
                                        @if ($type->description())
                                            <span class="block text-2xs leading-snug text-faint">{{ $type->description() }}</span>
                                        @endif
                                    </span>
                                </button>
                            @endforeach
                        @endforeach
                    </div>
                </x-dropdown>
            </div>

            <div class="px-4 py-4">
                @if (empty($blocks))
                    <x-empty-state icon="layer-group" title="No sections yet" description="Use “Add section” to choose what this template puts in a report." />
                @else
                    <ul x-data x-init="window.Sortable.create($el, { handle: '.drag-handle', animation: 150, onEnd() {
                            $wire.reorder(Array.from($el.children).map(c => parseInt(c.getAttribute('data-index'))));
                        }})"
                        class="space-y-2">
                        @foreach ($blocks as $i => $block)
                            @php
                                $type = $registry->find($block['type']);
                                $blockLabel = ($block['heading'] ?? '') !== '' ? $block['heading'] : ($type?->label() ?? $block['type']);
                            @endphp
                            <li data-index="{{ $i }}" wire:key="tblock-{{ $i }}-{{ $block['type'] }}"
                                x-data="{ open: false }"
                                class="rounded-lg border border-line bg-paper/40">
                                {{-- Compact header --}}
                                <div class="flex items-center gap-2 px-3 py-2">
                                    <button type="button" class="drag-handle cursor-grab text-faint hover:text-muted" aria-label="Drag to reorder {{ $blockLabel }}" title="Drag to reorder">
                                        <x-icon name="grip-dots-vertical" class="h-3.5 w-3.5" />
                                    </button>
                                    <span class="inline-block h-4 w-4 shrink-0 align-middle" aria-hidden="true">{!! \App\Support\ReportIcons::html($type?->icon() ?? 'document', '#8a6a2c') !!}</span>
                                    <button type="button" @click="open = !open" x-bind:aria-expanded="open ? 'true' : 'false'" class="min-w-0 flex-1 truncate text-left text-sm font-medium text-ink">
                                        {{ $blockLabel }}
                                    </button>

                                    {{-- Action bar --}}
                                    <div class="flex shrink-0 items-center">
                                        <x-icon-button icon="chevron-up" wire:click="moveBlock({{ $i }}, 'up')" :disabled="$loop->first" label="Move {{ $blockLabel }} up" />
                                        <x-icon-button icon="chevron-down" wire:click="moveBlock({{ $i }}, 'down')" :disabled="$loop->last" label="Move {{ $blockLabel }} down" />
                                        <x-dropdown>
                                            <x-slot:trigger>
                                                <button type="button" class="cr-btn-icon" aria-label="More actions for {{ $blockLabel }}">
                                                    <x-icon name="ellipsis-horizontal" class="h-4 w-4" />
                                                </button>
                                            </x-slot:trigger>
                                            <x-dropdown-item wire:click="duplicateBlock({{ $i }})" icon="document-duplicate">Duplicate</x-dropdown-item>
                                            <div class="cr-menu-separator"></div>
                                            <x-confirm-button role="menuitem" class="cr-menu-item cr-menu-item-danger"
                                                action="removeBlock({{ $i }})"
                                                title="Remove this section?"
                                                message="“{{ $blockLabel }}” is removed from this template."
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
                                    <x-field label="Heading" :for="'tblock-'.$i.'-heading'" :name="'blocks.'.$i.'.heading'">
                                        <input wire:model.live="blocks.{{ $i }}.heading" id="tblock-{{ $i }}-heading"
                                               placeholder="{{ $type?->label() ?? 'Section heading' }}" class="cr-input text-sm">
                                    </x-field>

                                    @if ($type && $type->builderOptions() !== [])
                                        <div class="space-y-3 rounded-lg border border-line bg-surface p-3">
                                            <p class="cr-eyebrow">Options</p>
                                            @foreach ($type->builderOptions() as $opt)
                                                <div wire:key="topt-{{ $i }}-{{ $opt->key }}">
                                                    @php $optId = "topt-{$i}-{$opt->key}"; @endphp
                                                    @if ($opt->type === 'toggle')
                                                        <x-checkbox wire:model="blocks.{{ $i }}.config.{{ $opt->key }}" :id="$optId" :label="$opt->label" />
                                                    @elseif ($opt->type === 'number')
                                                        <label for="{{ $optId }}" class="block text-xs font-medium text-muted">{{ $opt->label }}</label>
                                                        <input type="number" id="{{ $optId }}" min="{{ $opt->min }}" max="{{ $opt->max }}" wire:model="blocks.{{ $i }}.config.{{ $opt->key }}" class="cr-input mt-1 text-sm">
                                                    @elseif ($opt->type === 'select')
                                                        <label for="{{ $optId }}" class="block text-xs font-medium text-muted">{{ $opt->label }}</label>
                                                        <select id="{{ $optId }}" wire:model="blocks.{{ $i }}.config.{{ $opt->key }}" class="cr-input mt-1 text-sm">
                                                            @foreach ($opt->choices as $value => $choiceLabel)
                                                                <option value="{{ $value }}">{{ $choiceLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif ($opt->type === 'multiselect')
                                                        <fieldset>
                                                            <legend class="block text-xs font-medium text-muted">{{ $opt->label }}</legend>
                                                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                                                                @foreach ($opt->choices as $value => $choiceLabel)
                                                                    <x-checkbox value="{{ $value }}" wire:model="blocks.{{ $i }}.config.{{ $opt->key }}" :label="$choiceLabel" />
                                                                @endforeach
                                                            </div>
                                                        </fieldset>
                                                    @endif
                                                    @if ($opt->help)<p class="cr-help">{{ $opt->help }}</p>@endif
                                                </div>
                                            @endforeach
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
</div>
