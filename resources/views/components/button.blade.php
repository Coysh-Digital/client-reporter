@props([
    'variant' => 'secondary',   // primary | secondary | danger | ghost
    'size' => 'md',             // sm | md
    'href' => null,
    'icon' => null,
    'label' => null,            // required for icon-only buttons (becomes the accessible name)
    'type' => 'button',
    'navigate' => true,
])

@php
    $classes = ['cr-btn', 'cr-btn-'.$variant, 'cr-btn-sm' => $size === 'sm'];
    $iconOnly = $slot->isEmpty() && $icon !== null;
@endphp

@if ($href)
    <a href="{{ $href }}" @if ($navigate) wire:navigate @endif {{ $attributes->class($classes) }} @if ($iconOnly && $label) aria-label="{{ $label }}" @endif>
        @if ($icon)<x-icon :name="$icon" class="h-3.5 w-3.5" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }} @if ($iconOnly && $label) aria-label="{{ $label }}" @endif>
        @if ($icon)<x-icon :name="$icon" class="h-3.5 w-3.5" />@endif
        {{ $slot }}
    </button>
@endif
