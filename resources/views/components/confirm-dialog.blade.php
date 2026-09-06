{{--
    The one confirmation dialog for the whole app, included once in the layout.
    Trigger it with <x-confirm-button> (or dispatch a `confirm` window event with
    {title, message, confirm, cancel, danger, then}).
--}}
<div x-data="crConfirm()" x-on:confirm.window="ask($event.detail)">
    <dialog x-ref="dialog" x-on:click="backdrop($event)" class="cr-dialog cr-dialog-sm" role="alertdialog" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message">
        <div class="px-5 pb-2 pt-5">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                      x-bind:class="danger ? 'bg-danger-soft text-danger' : 'bg-accent-soft text-accent'">
                    <x-icon name="exclamation-triangle" class="h-4 w-4" />
                </span>
                <div class="min-w-0">
                    <h2 id="confirm-dialog-title" class="font-serif text-lg font-semibold text-ink" x-text="title"></h2>
                    <p id="confirm-dialog-message" class="mt-1 text-sm text-muted" x-text="message" x-show="message"></p>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 px-5 pb-5 pt-3">
            <button type="button" x-on:click="close()" class="cr-btn cr-btn-secondary" x-text="cancelLabel"></button>
            <button type="button" x-ref="confirm" x-on:click="accept()" class="cr-btn"
                    x-bind:class="danger ? 'cr-btn-danger-solid' : 'cr-btn-primary'" x-text="confirmLabel"></button>
        </div>
    </dialog>
</div>
