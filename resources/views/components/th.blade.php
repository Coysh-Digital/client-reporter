@props([
    'sort' => null,       // the sortable key; omit for a plain heading
    'current' => null,    // the component's active sort key
    'direction' => 'asc',
    'align' => 'left',
])

@php
    $active = $sort !== null && $sort === $current;
    $ariaSort = $active ? ($direction === 'desc' ? 'descending' : 'ascending') : ($sort !== null ? 'none' : null);
@endphp

<th scope="col" @if ($ariaSort) aria-sort="{{ $ariaSort }}" @endif {{ $attributes->class(['text-right' => $align === 'right', 'text-center' => $align === 'center']) }}>
    @if ($sort !== null)
        <button type="button" wire:click="sortBy('{{ $sort }}')" class="cr-table-sort" @class(['text-ink' => $active])>
            <span>{{ $slot }}</span>
            <x-icon :name="$active ? ($direction === 'desc' ? 'arrow-down' : 'arrow-up') : 'arrows-up-down'" class="h-3 w-3 {{ $active ? '' : 'opacity-50' }}" />
        </button>
    @else
        {{ $slot }}
    @endif
</th>
