@props([
    'icon',
    'label',                 // the accessible name (also the tooltip)
    'danger' => false,
    'href' => null,
])

@php $classes = ['cr-btn-icon', 'cr-btn-icon-danger' => $danger]; @endphp

@if ($href)
    <a href="{{ $href }}" wire:navigate aria-label="{{ $label }}" title="{{ $label }}" {{ $attributes->class($classes) }}>
        <x-icon :name="$icon" class="h-4 w-4" />
    </a>
@else
    <button type="button" aria-label="{{ $label }}" title="{{ $label }}" {{ $attributes->class($classes) }}>
        <x-icon :name="$icon" class="h-4 w-4" />
    </button>
@endif
