@props(['name'])

@php $icon = \App\Support\Icons::get($name); @endphp

@if ($icon)
    <svg {{ $attributes->merge(['class' => 'h-4 w-4', 'fill' => 'currentColor', 'aria-hidden' => 'true']) }} viewBox="0 0 {{ $icon['width'] }} {{ $icon['height'] }}">
        @foreach ($icon['paths'] as $path)
            <path d="{{ $path['d'] }}"@if ($path['evenodd']) fill-rule="evenodd" clip-rule="evenodd"@endif/>
        @endforeach
    </svg>
@endif
