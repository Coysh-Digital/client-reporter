@props([
    'options' => [],        // key => label
    'value' => null,        // the selected key
    'action' => null,       // Livewire method to call with the key…
    'model' => null,        // …or a Livewire property to $set
    'variant' => 'soft',    // soft | solid
    'label' => 'Filter',    // accessible name for the group
])

<div {{ $attributes->class(['cr-segmented', 'cr-segmented-solid' => $variant === 'solid']) }} role="group" aria-label="{{ $label }}">
    @foreach ($options as $key => $optionLabel)
        <button type="button"
                class="cr-segmented-item"
                aria-pressed="{{ (string) $value === (string) $key ? 'true' : 'false' }}"
                @if ($action) wire:click="{{ $action }}('{{ $key }}')" @elseif ($model) wire:click="$set('{{ $model }}', '{{ $key }}')" @endif>
            {{ $optionLabel }}
        </button>
    @endforeach
</div>
