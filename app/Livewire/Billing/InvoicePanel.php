<?php

declare(strict_types=1);

namespace App\Livewire\Billing;

use App\Billing\BillingSyncer;
use App\Enums\InvoiceStatus;
use App\Integrations\Support\IntegrationException;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Manually-entered billing for a client — invoices the agency raised against
 * them, embedded on the client page. Feeds the report's Billing & invoices
 * block; there is no external accounting integration, so this works regardless
 * of which invoicing tool the agency actually uses.
 */
class InvoicePanel extends Component
{
    public Client $client;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $number = '';

    public string $description = '';

    public string $amount = '';

    public string $currency = 'GBP';

    public string $status = 'sent';

    public string $issued_at = '';

    public string $due_at = '';

    public string $paid_at = '';

    public function mount(Client $client): void
    {
        $this->client = $client;
    }

    public function startCreate(): void
    {
        $this->authorize('manage-clients');

        $this->reset(['editingId', 'number', 'description', 'amount', 'due_at', 'paid_at']);
        $this->currency = 'GBP';
        $this->status = InvoiceStatus::Sent->value;
        $this->issued_at = now()->toDateString();
        $this->showForm = true;
    }

    public function startEdit(int $invoiceId): void
    {
        $this->authorize('manage-clients');

        $invoice = $this->client->invoices()->findOrFail($invoiceId);
        abort_if($invoice->isSynced(), 403, 'This invoice is synced from a billing connection and can’t be edited here.');

        $this->editingId = $invoice->id;
        $this->number = $invoice->number;
        $this->description = (string) $invoice->description;
        $this->amount = (string) $invoice->amount;
        $this->currency = (string) $invoice->currency;
        $this->status = $invoice->status->value;
        $this->issued_at = $invoice->issued_at->toDateString();
        $this->due_at = $invoice->due_at?->toDateString() ?? '';
        $this->paid_at = $invoice->paid_at?->toDateString() ?? '';
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->reset(['editingId', 'number', 'description', 'amount', 'due_at', 'paid_at']);
    }

    public function save(): void
    {
        $this->authorize('manage-clients');

        $validated = $this->validate([
            'number' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['required', 'string', 'in:'.implode(',', array_column(InvoiceStatus::cases(), 'value'))],
            'issued_at' => ['required', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $validated['currency'] = $validated['currency'] !== null ? strtoupper($validated['currency']) : null;
        $validated['due_at'] = $validated['due_at'] ?: null;
        $validated['paid_at'] = $validated['paid_at'] ?: null;

        $invoice = $this->editingId !== null
            ? $this->client->invoices()->findOrFail($this->editingId)
            : new Invoice(['client_id' => $this->client->id]);

        $invoice->fill($validated)->save();

        $this->cancel();
        $this->dispatch('toast', message: 'Invoice saved.', type: 'ok');
    }

    public function markPaid(int $invoiceId): void
    {
        $this->authorize('manage-clients');

        $invoice = $this->client->invoices()->findOrFail($invoiceId);
        abort_if($invoice->isSynced(), 403, 'This invoice is synced from a billing connection and can’t be edited here.');

        $invoice->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => Carbon::today(),
        ]);

        $this->dispatch('toast', message: 'Invoice marked paid.', type: 'ok');
    }

    public function delete(int $invoiceId): void
    {
        $this->authorize('manage-clients');

        $invoice = $this->client->invoices()->findOrFail($invoiceId);
        abort_if($invoice->isSynced(), 403, 'This invoice is synced from a billing connection and can’t be deleted here.');

        $invoice->delete();

        $this->dispatch('toast', message: 'Invoice deleted.', type: 'ok');
    }

    public function syncNow(BillingSyncer $syncer): void
    {
        $this->authorize('manage-clients');

        $link = $this->client->billingConnection;
        if ($link === null) {
            return;
        }

        try {
            $count = $syncer->syncOne($link);
            $this->dispatch('toast', message: "Synced {$count} invoice(s) from {$link->workspaceIntegration->name}.", type: 'ok');
        } catch (IntegrationException $e) {
            $this->dispatch('toast', message: 'Sync failed: '.$e->getMessage(), type: 'error');
        }
    }

    public function disconnectBilling(): void
    {
        $this->authorize('manage-clients');

        $this->client->billingConnection?->delete();

        $this->dispatch('toast', message: 'Billing connection removed. Already-synced invoices were kept.', type: 'ok');
    }

    public function render(): mixed
    {
        $invoices = $this->client->invoices()->orderByDesc('issued_at')->get();

        return view('livewire.billing.invoice-panel', [
            'invoices' => $invoices,
            'totals' => $this->totals($invoices),
            'recurringInvoices' => $this->client->recurringInvoices()
                ->orderByRaw('next_recurs_on is null, next_recurs_on')
                ->get(),
            'statuses' => InvoiceStatus::cases(),
            'billingConnection' => $this->client->billingConnection,
        ]);
    }

    /**
     * Outstanding, overdue and paid-this-year figures, in the client's most
     * common currency (mixed-currency clients are summed as-is and labelled).
     *
     * @param  Collection<int, Invoice>  $invoices
     * @return array{currency: string|null, outstanding: float, overdue: float, paidYtd: float, mixed: bool}
     */
    private function totals($invoices): array
    {
        $currencies = $invoices->pluck('currency')->filter()->countBy();
        $currency = $currencies->isNotEmpty() ? (string) $currencies->sortDesc()->keys()->first() : null;

        $outstanding = $invoices->filter(fn (Invoice $i): bool => $i->status === InvoiceStatus::Sent);

        return [
            'currency' => $currency,
            'outstanding' => (float) $outstanding->sum('amount'),
            'overdue' => (float) $outstanding->filter(fn (Invoice $i): bool => $i->isOverdue())->sum('amount'),
            'paidYtd' => (float) $invoices
                ->filter(fn (Invoice $i): bool => $i->status === InvoiceStatus::Paid && ($i->paid_at ?? $i->issued_at)->isSameYear(now()))
                ->sum('amount'),
            'mixed' => $currencies->count() > 1,
        ];
    }
}
