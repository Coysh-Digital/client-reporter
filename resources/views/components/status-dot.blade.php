@props([
    'variant' => 'neutral', // ok | warn | danger | info | accent | neutral
    'label' => null,
])

@php
    $color = [
        'ok' => 'var(--color-ok)',
        'warn' => 'var(--color-warn)',
        'danger' => 'var(--color-danger)',
        'info' => 'var(--color-info)',
        'accent' => 'var(--color-accent)',
        'neutral' => 'var(--color-faint)',
    ][$variant] ?? 'var(--color-faint)';
@endphp

@if ($label !== null || ! $slot->isEmpty())
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 text-xs']) }} style="color:{{ $color }};">
        <span class="shrink-0" style="width:8px;height:8px;border-radius:999px;background:{{ $color }};"></span>
        <span class="truncate">{{ $label ?? $slot }}</span>
    </span>
@else
    <span {{ $attributes->merge(['class' => 'inline-block shrink-0']) }} style="width:8px;height:8px;border-radius:999px;background:{{ $color }};"></span>
@endif
