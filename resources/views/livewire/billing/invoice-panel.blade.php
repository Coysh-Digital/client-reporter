@php use App\Support\Format; @endphp
<div>
    @if (session('status'))
        <div class="mb-4 rounded-md bg-ok-soft px-3 py-2 text-sm text-ok">{{ session('status') }}</div>
    @endif

    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-ink">Billing</h2>
        @can('manage-clients')
            @unless ($showForm)
                <button wire:click="startCreate" class="cr-btn cr-btn-secondary text-xs">+ Add invoice</button>
            @endunless
        @endcan
    </div>

    @if ($billingConnection)
        <div class="mb-4 flex items-center justify-between gap-3 rounded-md bg-accent-soft px-3 py-2 text-xs" style="color:var(--color-accent)">
            <span>
                Synced from <strong>{{ $billingConnection->workspaceIntegration->name }}</strong> · Contact: {{ $billingConnection->external_contact_name }}
                @if ($billingConnection->last_synced_at)
                    · Last synced {{ $billingConnection->last_synced_at->diffForHumans() }}
                @endif
            </span>
            @can('manage-clients')
                <span class="flex shrink-0 items-center gap-3 font-semibold">
                    <button wire:click="syncNow" wire:loading.attr="disabled" wire:target="syncNow">
                        <span wire:loading.remove wire:target="syncNow">Sync now</span>
                        <span wire:loading wire:target="syncNow">Syncing…</span>
                    </button>
                    <button wire:click="disconnectBilling" wire:confirm="Stop syncing invoices for this client? Already-synced invoices are kept.">Disconnect</button>
                </span>
            @endcan
        </div>
    @endif

    @can('manage-clients')
        @if ($showForm)
            <form wire:submit="save" class="cr-card mb-4 px-5 py-4 space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="cr-label">Invoice number</label>
                        <input wire:model="number" type="text" class="cr-input" placeholder="INV-000123">
                        @error('number') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="cr-label">Status</label>
                        <select wire:model="status" class="cr-input">
                            @foreach ($statuses as $s)
                                <option value="{{ $s->value }}">{{ $s->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="cr-label">Description <span class="text-faint">(optional)</span></label>
                    <input wire:model="description" type="text" class="cr-input" placeholder="Monthly retainer">
                    @error('description') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="cr-label">Amount</label>
                        <input wire:model="amount" type="number" step="0.01" min="0" class="cr-input">
                        @error('amount') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="cr-label">Currency</label>
                        <input wire:model="currency" type="text" maxlength="3" class="cr-input" placeholder="GBP">
                        @error('currency') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div></div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="cr-label">Issued</label>
                        <input wire:model="issued_at" type="date" class="cr-input">
                        @error('issued_at') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="cr-label">Due <span class="text-faint">(optional)</span></label>
                        <input wire:model="due_at" type="date" class="cr-input">
                        @error('due_at') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="cr-label">Paid on <span class="text-faint">(optional)</span></label>
                        <input wire:model="paid_at" type="date" class="cr-input">
                        @error('paid_at') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex items-center gap-3 border-t border-line pt-4">
                    <button type="submit" class="cr-btn cr-btn-primary">{{ $editingId ? 'Save invoice' : 'Add invoice' }}</button>
                    <button type="button" wire:click="cancel" class="cr-btn cr-btn-secondary">Cancel</button>
                </div>
            </form>
        @endif
    @endcan

    @if ($invoices->isEmpty())
        <x-empty-state title="No invoices yet" description="Invoices you add here also feed the Billing & invoices report block." />
    @else
        <div class="cr-card divide-y divide-line">
            @foreach ($invoices as $invoice)
                <div wire:key="invoice-{{ $invoice->id }}" class="flex items-center justify-between gap-4 px-5 py-3.5">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-ink">{{ $invoice->number }}</span>
                            <x-badge :variant="$invoice->isOverdue() ? 'danger' : $invoice->status->badge()">
                                {{ $invoice->isOverdue() ? 'Overdue' : $invoice->status->label() }}
                            </x-badge>
                            @if ($invoice->isSynced())
                                <span class="text-[11px] text-faint">via {{ ucfirst($invoice->source) }}</span>
                            @endif
                        </div>
                        <div class="mt-0.5 truncate text-xs text-muted">
                            {{ $invoice->description ?: 'No description' }} · Issued {{ $invoice->issued_at->format('d M Y') }}
                            @if ($invoice->due_at) · Due {{ $invoice->due_at->format('d M Y') }} @endif
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <span class="tnum text-sm font-semibold text-ink">{{ Format::money((float) $invoice->amount, $invoice->currency) }}</span>
                        @can('manage-clients')
                            @unless ($invoice->isSynced())
                                <div class="flex items-center gap-2 text-xs">
                                    @if ($invoice->status->value !== 'paid')
                                        <button wire:click="markPaid({{ $invoice->id }})" class="text-muted hover:text-ink">Mark paid</button>
                                    @endif
                                    <button wire:click="startEdit({{ $invoice->id }})" class="text-muted hover:text-ink">Edit</button>
                                    <button wire:click="delete({{ $invoice->id }})" wire:confirm="Delete invoice {{ $invoice->number }}?" class="text-danger hover:underline">Delete</button>
                                </div>
                            @endunless
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($recurringInvoices->isNotEmpty())
        <div class="mt-6">
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-faint">Upcoming (recurring)</h3>
            <div class="cr-card divide-y divide-line">
                @foreach ($recurringInvoices as $recurring)
                    <div wire:key="recurring-{{ $recurring->id }}" class="flex items-center justify-between gap-4 px-5 py-3.5">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-ink">{{ $recurring->frequency ?: 'Recurring' }}</span>
                                @unless ($recurring->isActive())
                                    <x-badge variant="neutral">{{ $recurring->status ?: 'Inactive' }}</x-badge>
                                @endunless
                            </div>
                            <div class="mt-0.5 truncate text-xs text-muted">
                                @if ($recurring->next_recurs_on)
                                    Next {{ $recurring->next_recurs_on->format('d M Y') }}
                                @else
                                    No upcoming date
                                @endif
                                @if ($recurring->ends_on) · Ends {{ $recurring->ends_on->format('d M Y') }} @endif
                            </div>
                        </div>
                        <span class="tnum text-sm font-semibold text-ink">{{ Format::money((float) $recurring->amount, $recurring->currency) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
