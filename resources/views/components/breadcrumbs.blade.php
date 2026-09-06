@props(['items' => []])

{{-- items: [['label' => 'Clients', 'href' => route('clients.index')], ['label' => 'Acme']] --}}
<nav aria-label="Breadcrumb" {{ $attributes->class(['cr-breadcrumbs']) }}>
    <ol class="flex flex-wrap items-center gap-1.5">
        @foreach ($items as $item)
            <li class="flex items-center gap-1.5">
                @if (! $loop->first)
                    <span aria-hidden="true" class="text-faint-decor">/</span>
                @endif
                @if (! empty($item['href']) && ! $loop->last)
                    <a href="{{ $item['href'] }}" wire:navigate>{{ $item['label'] }}</a>
                @else
                    <span @if ($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
