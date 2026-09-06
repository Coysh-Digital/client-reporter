{{--
    The "Add section" dropdown. Shows blocks the site can feed now (icon + label
    + description), then any that need an integration first, greyed with a hint.
    Expects $grouped, $unavailableGrouped and $connectedKeys in scope.
--}}
<x-dropdown width="w-80">
    <x-slot:trigger>
        <x-button icon="plus">Add section</x-button>
    </x-slot:trigger>
    <div class="max-h-[28rem] overflow-y-auto">
        @forelse ($grouped as $group => $types)
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
        @empty
            <p class="px-3 py-3 text-center text-xs text-faint">No sections available yet.</p>
        @endforelse

        @php $unavailable = collect($unavailableGrouped)->flatten(); @endphp
        @if ($unavailable->isNotEmpty())
            <div class="cr-menu-separator"></div>
            <p class="cr-menu-heading">Needs an integration</p>
            @foreach ($unavailable as $type)
                <div class="flex items-start gap-2.5 px-3 py-1.5 opacity-70" title="{{ $this->availabilityHint($type, $connectedKeys) }}">
                    <span class="mt-0.5 inline-block h-4 w-4 shrink-0 grayscale" aria-hidden="true">{!! \App\Support\ReportIcons::html($type->icon(), '#8b857a') !!}</span>
                    <span class="min-w-0">
                        <span class="block text-sm text-muted">{{ $type->label() }}</span>
                        <span class="block text-2xs leading-snug text-warn">{{ $this->availabilityHint($type, $connectedKeys) }}</span>
                    </span>
                </div>
            @endforeach
            <div class="cr-menu-separator"></div>
            <x-dropdown-item :href="route('sites.show', $report->site)" icon="plug">Manage this site’s integrations</x-dropdown-item>
        @endif
    </div>
</x-dropdown>
