<div>
    <div class="mb-2 text-sm text-faint">
        <a href="{{ route('templates.index') }}" wire:navigate class="hover:text-ink">Templates</a>
        <span style="color:var(--color-line-strong);">/</span> {{ $template ? 'Edit' : 'New' }}
    </div>

    <x-page-header :title="$template ? 'Edit template' : 'New template'" subtitle="Arrange the sections this template adds to a report.">
        <x-slot:actions>
            <a href="{{ route('templates.index') }}" wire:navigate class="cr-btn cr-btn-secondary">Cancel</a>
            <button wire:click="save" class="cr-btn cr-btn-primary">
                <span wire:loading.remove wire:target="save">Save template</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </x-slot:actions>
    </x-page-header>

    <div class="mx-auto max-w-3xl space-y-6">
        {{-- Details --}}
        <div class="cr-panel">
            <div class="border-b border-line px-5 py-3.5"><h2 class="cr-eyebrow">Template details</h2></div>
            <div class="space-y-4 px-5 py-5">
                <div>
                    <label class="cr-label">Name</label>
                    <input wire:model="name" class="cr-input" placeholder="e.g. Standard monthly care report">
                    @error('name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="cr-label">Description</label>
                    <input wire:model="description" class="cr-input" placeholder="Optional — what this template is for">
                    @error('description') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Sections --}}
        <div class="cr-panel">
            <div class="flex items-center justify-between border-b border-line px-5 py-3">
                <h2 class="cr-eyebrow">Sections</h2>
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" type="button" class="cr-btn cr-btn-secondary text-sm">+ Add section</button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                         class="absolute right-0 z-10 mt-1 max-h-80 w-64 overflow-y-auto rounded-lg border border-line bg-surface py-1 shadow-lg">
                        @foreach ($grouped as $group => $types)
                            <p class="px-3 py-1 text-[10.5px] font-bold uppercase tracking-[0.07em] text-faint">{{ $group }}</p>
                            @foreach ($types as $type)
                                <button type="button" wire:click="addBlock('{{ $type->type() }}')" @click="open = false"
                                        class="block w-full px-3 py-1.5 text-left text-sm hover:bg-paper">
                                    <span class="text-ink">{{ $type->label() }}</span>
                                </button>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="px-4 py-4">
                @if (empty($blocks))
                    <p class="py-6 text-center text-sm text-muted">No sections yet. Add your first section above.</p>
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
                                    <button type="button" class="drag-handle mt-0.5 cursor-grab text-faint hover:text-muted" title="Drag to reorder">⠿</button>
                                    <div class="min-w-0 flex-1">
                                        <span class="text-[11px] font-bold uppercase tracking-wide text-faint">{{ $type?->label() ?? $block['type'] }}</span>
                                        <input wire:model="blocks.{{ $i }}.heading" placeholder="Section heading" class="cr-input mt-2 text-sm">

                                        @if ($type && $type->options() !== [])
                                            <div x-data="{ open: false }" class="mt-2">
                                                <button type="button" @click="open = !open" class="text-xs font-semibold" style="color:var(--color-accent)">
                                                    <span x-show="!open">Options</span><span x-show="open" x-cloak>Hide options</span>
                                                </button>
                                                <div x-show="open" x-cloak class="mt-2 space-y-3 rounded-lg border border-line bg-surface p-3">
                                                    @foreach ($type->options() as $opt)
                                                        <div wire:key="topt-{{ $i }}-{{ $opt->key }}">
                                                            @if ($opt->type === 'toggle')
                                                                <label class="flex items-center gap-2 text-xs text-ink">
                                                                    <input type="checkbox" wire:model="blocks.{{ $i }}.config.{{ $opt->key }}" class="rounded border-line-strong text-accent focus:ring-accent">
                                                                    {{ $opt->label }}
                                                                </label>
                                                            @elseif ($opt->type === 'number')
                                                                <label class="block text-xs font-medium text-muted">{{ $opt->label }}</label>
                                                                <input type="number" min="{{ $opt->min }}" max="{{ $opt->max }}" wire:model="blocks.{{ $i }}.config.{{ $opt->key }}" class="cr-input mt-1 text-sm">
                                                            @elseif ($opt->type === 'select')
                                                                <label class="block text-xs font-medium text-muted">{{ $opt->label }}</label>
                                                                <select wire:model="blocks.{{ $i }}.config.{{ $opt->key }}" class="cr-input mt-1 text-sm">
                                                                    @foreach ($opt->choices as $value => $choiceLabel)
                                                                        <option value="{{ $value }}">{{ $choiceLabel }}</option>
                                                                    @endforeach
                                                                </select>
                                                            @elseif ($opt->type === 'multiselect')
                                                                <span class="block text-xs font-medium text-muted">{{ $opt->label }}</span>
                                                                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                                                                    @foreach ($opt->choices as $value => $choiceLabel)
                                                                        <label class="flex items-center gap-1.5 text-xs text-ink">
                                                                            <input type="checkbox" value="{{ $value }}" wire:model="blocks.{{ $i }}.config.{{ $opt->key }}" class="rounded border-line-strong text-accent focus:ring-accent">
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
                                    <button wire:click="removeBlock({{ $i }})" class="text-xs text-danger hover:underline">Remove</button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
