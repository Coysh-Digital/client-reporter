@props([
    'href' => null,
    'icon' => null,
    'danger' => false,
    'navigate' => true,
])

@php $classes = ['cr-menu-item', 'cr-menu-item-danger' => $danger]; @endphp

@if ($href)
    <a href="{{ $href }}" role="menuitem" @if ($navigate) wire:navigate @endif x-on:click="close(false)" {{ $attributes->class($classes) }}>
        @if ($icon)<x-icon :name="$icon" class="h-3.5 w-3.5 shrink-0 text-faint" />@endif
        <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    </a>
@else
    <button type="button" role="menuitem" x-on:click="close()" {{ $attributes->class($classes) }}>
        @if ($icon)<x-icon :name="$icon" class="h-3.5 w-3.5 shrink-0 text-faint" />@endif
        <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    </button>
@endif
