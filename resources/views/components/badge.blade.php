@props(['variant' => 'neutral'])

@php
    $styles = [
        'neutral' => 'background-color:var(--color-paper);color:var(--color-muted);',
        'ok' => 'background-color:var(--color-ok-soft);color:var(--color-ok);',
        'warn' => 'background-color:var(--color-warn-soft);color:var(--color-warn);',
        'danger' => 'background-color:var(--color-danger-soft);color:var(--color-danger);',
        'info' => 'background-color:var(--color-info-soft);color:var(--color-info);',
        'accent' => 'background-color:var(--color-accent-soft);color:var(--color-accent);',
    ][$variant] ?? '';
@endphp

<span {{ $attributes->merge(['class' => 'cr-badge']) }} style="{{ $styles }}">
    {{ $slot }}
</span>
