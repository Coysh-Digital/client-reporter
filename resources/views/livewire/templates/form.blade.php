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
                <x-dropdown width="w-64">
                    <x-slot:trigger>
                        <x-button icon="plus">Add section</x-button>
                    </x-slot:trigger>
                    <div class="max-h-80 overflow-y-auto">
                        @foreach ($grouped as $group => $types)
                            <p class="cr-menu-heading">{{ $group }}</p>
                            @foreach ($types as $type)
                                <x-dropdown-item wire:click="addBlock('{{ $type->type() }}')">{{ $type->label() }}</x-dropdown-item>
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
                            @php $type = $registry->find($block['type']); @endphp
                            <li data-index="{{ $i }}" wire:key="tblock-{{ $i }}-{{ $block['type'] }}"
                                class="rounded-lg border border-line bg-paper/40 px-4 py-3">
                                <div class="flex items-start gap-3">
                                    <button type="button" class="drag-handle mt-0.5 cursor-grab text-faint hover:text-muted" aria-label="Drag to reorder {{ $type?->label() ?? $block['type'] }}" title="Drag to reorder">⠿</button>
                                    <div class="min-w-0 flex-1">
                                        <span class="text-2xs font-bold uppercase tracking-wide text-faint">{{ $type?->label() ?? $block['type'] }}</span>
                                        <input wire:model="blocks.{{ $i }}.heading" placeholder="Section heading" aria-label="Heading for {{ $type?->label() ?? $block['type'] }}" class="cr-input mt-2 text-sm">

                                        @if ($type && $type->options() !== [])
                                            <div x-data="{ open: false }" class="mt-2">
                                                <button type="button" @click="open = !open" x-bind:aria-expanded="open ? 'true' : 'false'" class="cr-link text-xs">
                                                    <span x-show="!open">Options</span><span x-show="open" x-cloak>Hide options</span>
                                                </button>
                                                <div x-show="open" x-cloak class="mt-2 space-y-3 rounded-lg border border-line bg-surface p-3">
                                                    @foreach ($type->options() as $opt)
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
                                            </div>
                                        @endif
                                    </div>
                                    <x-icon-button icon="x-mark" wire:click="removeBlock({{ $i }})" label="Remove {{ $type?->label() ?? $block['type'] }}" danger />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
