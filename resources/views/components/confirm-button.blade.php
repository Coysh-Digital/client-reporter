@props([
    'action',                 // the $wire call, e.g. "delete(12)"
    'title' => 'Are you sure?',
    'message' => '',
    'confirm' => 'Confirm',
    'cancel' => 'Cancel',
    'danger' => false,
])

{{-- Opens the shared confirm dialog; the `then` closure runs the Livewire action in this component's scope. --}}
<button type="button"
        x-on:click.prevent="$dispatch('confirm', { title: @js($title), message: @js($message), confirm: @js($confirm), cancel: @js($cancel), danger: @js($danger), then: () => $wire.{{ $action }} })"
        {{ $attributes }}>
    {{ $slot }}
</button>
