@props([
    'label',
    'for',                 // the id of the control inside the slot
    'name' => null,        // the validation key (defaults to `for`)
    'help' => null,
    'required' => false,
    'optional' => false,
])

@php $errorKey = $name ?? $for; @endphp

<div {{ $attributes }}>
    <label for="{{ $for }}" class="cr-label">
        {{ $label }}
        @if ($required)<span class="text-danger" aria-hidden="true">*</span><span class="sr-only">(required)</span>@endif
        @if ($optional)<span class="font-normal text-faint">(optional)</span>@endif
    </label>
    {{ $slot }}
    @error($errorKey)
        <p class="cr-error" id="{{ $for }}-error">{{ $message }}</p>
    @enderror
    @if ($help)
        <p class="cr-help" id="{{ $for }}-help">{{ $help }}</p>
    @endif
</div>
