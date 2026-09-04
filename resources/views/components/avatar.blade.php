@props([
    'name' => '',
    'size' => 'md',   // sm | md | lg
    'shape' => 'rounded', // rounded | circle
    'icon' => null,   // optional logo URL — renders instead of the initials tile
])

@php
    // Initials: first letters of the first two words, else first two chars.
    $clean = trim((string) $name);
    if ($clean === '') {
        $initials = '—';
    } else {
        $words = preg_split('/[\s&]+/', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) >= 2) {
            $initials = mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1));
        } else {
            $initials = mb_strtoupper(mb_substr($clean, 0, 2));
        }
    }

    // Deterministic tint/ink pair from a small, warm palette.
    $palette = [
        ['#efe6d9', '#8a6a2c'],
        ['#e7e2ee', '#4b4a7a'],
        ['#dfeae4', '#2f6146'],
        ['#e2e6ec', '#3a4a5a'],
        ['#ece3db', '#8a5a2c'],
        ['#e6e2dd', '#6c675f'],
        ['#e6e1ec', '#5b4b7a'],
    ];
    $idx = $clean === '' ? count($palette) - 1 : (crc32($clean) % count($palette));
    [$bg, $fg] = $palette[$idx];

    $dims = match ($size) {
        'sm' => 'width:20px;height:20px;font-size:10px;',
        'lg' => 'width:38px;height:38px;font-size:15px;',
        default => 'width:30px;height:30px;font-size:12px;',
    };
    $radius = $shape === 'circle' ? 'border-radius:999px;' : 'border-radius:' . ($size === 'lg' ? '9px' : ($size === 'sm' ? '5px' : '7px')) . ';';
    $iconPad = match ($size) {
        'sm' => '4px', 'lg' => '8px', default => '6px',
    };
@endphp

@if ($icon)
    <span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center']) }}
          style="{{ $dims }}{{ $radius }}background:#fff;border:1px solid var(--color-line);padding:{{ $iconPad }};box-sizing:border-box;">
        <img src="{{ $icon }}" alt="{{ $name }}" style="width:100%;height:100%;object-fit:contain;">
    </span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center font-serif font-semibold']) }}
          style="{{ $dims }}{{ $radius }}background:{{ $bg }};color:{{ $fg }};line-height:1;">{{ $initials }}</span>
@endif
