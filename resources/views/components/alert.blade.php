@props(['variant' => 'info', 'title' => null])

@php
    $icon = ['ok' => 'check-circle', 'warn' => 'exclamation-triangle', 'danger' => 'x-circle', 'info' => 'information-circle'][$variant] ?? 'information-circle';
@endphp

<div {{ $attributes->class(['cr-alert', 'cr-alert-'.$variant]) }} role="{{ $variant === 'danger' ? 'alert' : 'status' }}">
    <x-icon :name="$icon" class="mt-0.5 h-4 w-4 shrink-0" />
    <div class="min-w-0 flex-1">
        @if ($title)<p class="font-semibold">{{ $title }}</p>@endif
        <div>{{ $slot }}</div>
    </div>
    @isset($action)
        <div class="shrink-0">{{ $action }}</div>
    @endisset
</div>
