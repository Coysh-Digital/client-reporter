@props(['items' => [], 'label' => 'Sections'])

{{-- items: [['label' => 'General', 'href' => route('settings.edit'), 'active' => true], …] --}}
<nav aria-label="{{ $label }}" {{ $attributes->class(['cr-tabs']) }}>
    @foreach ($items as $item)
        <a href="{{ $item['href'] }}" wire:navigate class="cr-tab" @if (! empty($item['active'])) aria-current="page" @endif>{{ $item['label'] }}</a>
    @endforeach
</nav>
