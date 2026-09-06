@props([
    'name',                // opens on the `open-{name}` window event, closes on `close-{name}`
    'title',
    'size' => 'md',        // sm | md | lg
    'description' => null,
])

{{-- A native <dialog>: focus stays inside, Escape closes, the page behind is inert. --}}
<div x-data="crDialog(@js($name))" x-on:open-{{ $name }}.window="show()" x-on:close-{{ $name }}.window="close()" wire:ignore.self>
    <dialog x-ref="dialog" x-on:click="backdrop($event)" aria-labelledby="dialog-{{ $name }}-title"
            @if ($description) aria-describedby="dialog-{{ $name }}-description" @endif
            {{ $attributes->class(['cr-dialog', 'cr-dialog-sm' => $size === 'sm', 'cr-dialog-lg' => $size === 'lg']) }}>
        <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
            <div class="min-w-0">
                <h2 id="dialog-{{ $name }}-title" class="font-serif text-lg font-semibold text-ink">{{ $title }}</h2>
                @if ($description)
                    <p id="dialog-{{ $name }}-description" class="mt-0.5 text-sm text-muted">{{ $description }}</p>
                @endif
            </div>
            <button type="button" x-on:click="close()" class="cr-btn-icon -mr-2 -mt-1" aria-label="Close">
                <x-icon name="x-mark" class="h-4 w-4" />
            </button>
        </div>
        <div class="max-h-[70vh] overflow-y-auto px-5 py-4">
            {{ $slot }}
        </div>
        @isset($footer)
            <div class="flex items-center justify-end gap-2 border-t border-line px-5 py-3">
                {{ $footer }}
            </div>
        @endisset
    </dialog>
</div>
