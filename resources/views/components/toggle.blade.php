@props(['label', 'help' => null])

{{-- A real checkbox drives the switch, so wire:model, forms and screen readers all just work. --}}
<label class="cr-switch-wrap {{ $attributes->get('class') }}">
    <input type="checkbox" role="switch" {{ $attributes->except('class')->class(['cr-switch sr-only']) }}>
    <span class="cr-switch-track" aria-hidden="true"><span class="cr-switch-thumb"></span></span>
    <span class="min-w-0">
        <span class="block text-sm text-ink">{{ $label }}</span>
        @if ($help)<span class="block text-xs text-faint">{{ $help }}</span>@endif
    </span>
</label>
