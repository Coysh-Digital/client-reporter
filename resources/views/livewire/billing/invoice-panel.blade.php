@php use App\Support\Format; @endphp
<div>

    <div class="mb-3 flex items-center justify-between">
        <h2 class="font-serif text-base font-semibold text-ink">Billing</h2>
        @can('manage-clients')
            @unless ($showForm)
                <x-button size="sm" icon="plus" wire:click="startCreate">Add invoice</x-button>
            @endunless
        @endcan
    </div>

    @if ($invoices->isNotEmpty())
        <div class="mb-4 grid grid-cols-3 overflow-hidden rounded-xl border border-line bg-surface">
            <div class="border-r border-line px-4 py-3">
                <div class="cr-eyebrow">Outstanding</div>
                <div class="tnum mt-1 text-md font-semibold text-ink">{{ Format::money($totals['outstanding'], $totals['currency']) }}</div>
            </div>
            <div class="border-r border-line px-4 py-3">
                <div class="cr-eyebrow">Overdue</div>
                <div class="tnum mt-1 text-md font-semibold" style="color:var(--color-{{ $totals['overdue'] > 0 ? 'danger' : 'ink' }});">{{ Format::money($totals['overdue'], $totals['currency']) }}</div>
            </div>
            <div class="px-4 py-3">
                <div class="cr-eyebrow">Paid this year</div>
                <div class="tnum mt-1 text-md font-semibold text-ink">{{ Format::money($totals['paidYtd'], $totals['currency']) }}</div>
            </div>
            @if ($totals['mixed'])
                <p class="col-span-3 border-t border-line px-4 py-1.5 text-2xs text-faint">Invoices are in more than one currency; totals are summed as entered.</p>
            @endif
        </div>
    @endif

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
                    <x-confirm-button action="disconnectBilling" title="Stop syncing invoices?" message="Invoices already synced for this client are kept; new ones will no longer arrive." confirm="Disconnect" :danger="true">Disconnect</x-confirm-button>
                </span>
            @endcan
        </div>
    @endif

    @can('manage-clients')
        @if ($showForm)
            <form wire:submit="save" class="cr-card mb-4 px-5 py-4 space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Invoice number" for="invoice-number" name="number" required>
                        <input wire:model="number" id="invoice-number" type="text" class="cr-input" placeholder="INV-000123">
                    </x-field>
                    <x-field label="Status" for="invoice-status" name="status">
                        <select wire:model="status" id="invoice-status" class="cr-input">
                            @foreach ($statuses as $s)
                                <option value="{{ $s->value }}">{{ $s->label() }}</option>
                            @endforeach
                        </select>
                    </x-field>
                </div>

                <x-field label="Description" for="invoice-description" name="description" optional>
                    <input wire:model="description" id="invoice-description" type="text" class="cr-input" placeholder="Monthly retainer">
                </x-field>

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field label="Amount" for="invoice-amount" name="amount" required>
                        <input wire:model="amount" id="invoice-amount" type="number" step="0.01" min="0" class="cr-input">
                    </x-field>
                    <x-field label="Currency" for="invoice-currency" name="currency">
                        <input wire:model="currency" id="invoice-currency" type="text" maxlength="3" class="cr-input" placeholder="GBP">
                    </x-field>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field label="Issued" for="invoice-issued" name="issued_at" required>
                        <input wire:model="issued_at" id="invoice-issued" type="date" class="cr-input">
                    </x-field>
                    <x-field label="Due" for="invoice-due" name="due_at" optional>
                        <input wire:model="due_at" id="invoice-due" type="date" class="cr-input">
                    </x-field>
                    <x-field label="Paid on" for="invoice-paid" name="paid_at" optional>
                        <input wire:model="paid_at" id="invoice-paid" type="date" class="cr-input">
                    </x-field>
                </div>

                <div class="flex items-center gap-3 border-t border-line pt-4">
                    <x-button type="submit" variant="primary">{{ $editingId ? 'Save invoice' : 'Add invoice' }}</x-button>
                    <x-button wire:click="cancel">Cancel</x-button>
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
                                <span class="text-2xs text-faint">via {{ ucfirst($invoice->source) }}</span>
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
                                <div class="flex items-center gap-1">
                                    @if ($invoice->status->value !== 'paid')
                                        <x-button size="sm" variant="ghost" wire:click="markPaid({{ $invoice->id }})">Mark paid</x-button>
                                    @endif
                                    <x-icon-button icon="pencil-square" wire:click="startEdit({{ $invoice->id }})" label="Edit invoice {{ $invoice->number }}" />
                                    <x-confirm-button class="cr-btn-icon cr-btn-icon-danger" action="delete({{ $invoice->id }})" title="Delete invoice {{ $invoice->number }}?" message="This removes it from the client’s billing history and from the Billing report block." confirm="Delete invoice" :danger="true">
                                        <x-icon name="trash-can" class="h-4 w-4" /><span class="sr-only">Delete invoice {{ $invoice->number }}</span>
                                    </x-confirm-button>
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
            <h3 class="cr-eyebrow mb-2">Upcoming (recurring)</h3>
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
