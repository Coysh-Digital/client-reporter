{{--
    Rendered once in the layout. Session flashes (after a redirect) show inline;
    in-place actions dispatch a `toast` browser event instead.
--}}
@if (session('status') || session('error') || session('warning'))
    <div class="mb-5 space-y-2">
        @if (session('status'))
            <x-alert variant="ok">{{ session('status') }}</x-alert>
        @endif
        @if (session('warning'))
            <x-alert variant="warn">{{ session('warning') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif
    </div>
@endif

<div x-data="crToasts()" x-on:toast.window="push($event.detail)"
     class="pointer-events-none fixed bottom-4 right-4 z-[70] flex flex-col gap-2" role="status" aria-live="polite">
    <template x-for="toast in toasts" x-key="toast.id">
        <div class="cr-toast pointer-events-auto"
             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="translate-y-2 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <span class="inline-flex h-2 w-2 shrink-0 rounded-full"
                  x-bind:style="'background:var(--color-' + (toast.type === 'error' ? 'danger' : toast.type) + ');'"></span>
            <span class="min-w-0 flex-1" x-text="toast.message"></span>
            <button type="button" x-on:click="dismiss(toast.id)" class="cr-btn-icon -mr-2 h-7 w-7" aria-label="Dismiss">
                <x-icon name="x-mark" class="h-3.5 w-3.5" />
            </button>
        </div>
    </template>
</div>
