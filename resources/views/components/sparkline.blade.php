@props([
    'points' => [],       // list of numbers
    'width' => 96,
    'height' => 24,
    'label' => null,      // accessible description
])

@php
    $values = array_values(array_map('floatval', $points));
    $count = count($values);
    $min = $count ? min($values) : 0.0;
    $max = $count ? max($values) : 0.0;
    $span = max($max - $min, 0.000001);
    $coords = [];
    foreach ($values as $i => $value) {
        $x = $count > 1 ? ($i / ($count - 1)) * ($width - 2) + 1 : $width / 2;
        $y = $height - 2 - (($value - $min) / $span) * ($height - 4);
        $coords[] = round($x, 1).','.round($y, 1);
    }
@endphp

@if ($count >= 2)
    <svg {{ $attributes }} width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}"
         role="img" aria-label="{{ $label ?? 'Trend over the last '.$count.' points' }}" class="shrink-0 text-accent">
        <polyline fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" points="{{ implode(' ', $coords) }}" />
        <circle cx="{{ explode(',', end($coords))[0] }}" cy="{{ explode(',', end($coords))[1] }}" r="1.75" fill="currentColor" />
    </svg>
@endif
