@props(['label' => null, 'help' => null])

<label class="cr-check-wrap {{ $attributes->get('class') }}">
    <input type="checkbox" {{ $attributes->except('class')->class(['cr-checkbox']) }}>
    <span>
        <span>{{ $label ?? $slot }}</span>
        @if ($help)<span class="block text-xs font-normal text-faint">{{ $help }}</span>@endif
    </span>
</label>
