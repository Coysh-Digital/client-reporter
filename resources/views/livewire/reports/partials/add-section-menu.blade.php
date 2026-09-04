{{--
    The "Add section" dropdown. Shows blocks the site can feed now (icon + label
    + description), then any that need an integration first, greyed with a hint.
    Expects $grouped, $unavailableGrouped and $connectedKeys in scope.
--}}
<div x-data="{ open: false }" class="relative">
    <button @click="open = !open" type="button" class="cr-btn cr-btn-secondary text-sm">
        <x-icon name="plus" class="h-3 w-3" />
        Add section
    </button>
    <div x-show="open" @click.outside="open = false" x-cloak
         class="absolute right-0 z-10 mt-1 max-h-[28rem] w-80 overflow-y-auto rounded-lg border border-line bg-surface py-1 shadow-lg">
        @forelse ($grouped as $group => $types)
            <p class="px-3 pb-1 pt-2 text-[10.5px] font-bold uppercase tracking-[0.07em] text-faint">{{ $group }}</p>
            @foreach ($types as $type)
                <button type="button" wire:click="addBlock('{{ $type->type() }}')" @click="open = false"
                        class="flex w-full items-start gap-2.5 px-3 py-1.5 text-left hover:bg-paper">
                    <span class="mt-0.5 inline-block h-4 w-4 shrink-0">{!! \App\Support\ReportIcons::html($type->icon(), '#8a6a2c') !!}</span>
                    <span class="min-w-0">
                        <span class="block text-sm text-ink">{{ $type->label() }}</span>
                        @if ($type->description())
                            <span class="block text-[11px] leading-snug text-faint">{{ $type->description() }}</span>
                        @endif
                    </span>
                </button>
            @endforeach
        @empty
            <p class="px-3 py-3 text-center text-xs text-faint">No sections available yet.</p>
        @endforelse

        @php $unavailable = collect($unavailableGrouped)->flatten(); @endphp
        @if ($unavailable->isNotEmpty())
            <p class="mt-1 border-t border-line px-3 pb-1 pt-2 text-[10.5px] font-bold uppercase tracking-[0.07em] text-faint">Needs an integration</p>
            @foreach ($unavailable as $type)
                <div class="flex items-start gap-2.5 px-3 py-1.5 opacity-55" title="{{ $this->availabilityHint($type, $connectedKeys) }}">
                    <span class="mt-0.5 inline-block h-4 w-4 shrink-0 grayscale">{!! \App\Support\ReportIcons::html($type->icon(), '#8b857a') !!}</span>
                    <span class="min-w-0">
                        <span class="block text-sm text-muted">{{ $type->label() }}</span>
                        <span class="block text-[11px] leading-snug text-warn">{{ $this->availabilityHint($type, $connectedKeys) }}</span>
                    </span>
                </div>
            @endforeach
            <a href="{{ route('sites.show', $report->site) }}" wire:navigate @click="open = false"
               class="mt-1 block border-t border-line px-3 py-2 text-center text-xs font-semibold" style="color:var(--color-accent)">
                Manage this site’s integrations
            </a>
        @endif
    </div>
</div>
