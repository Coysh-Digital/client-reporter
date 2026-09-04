<?php

declare(strict_types=1);

namespace App\Reporting\Blocks;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Site;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;
use App\Reporting\Support\Insight;
use App\Support\Format;

/**
 * The agency's own billing of the client for the period — manually entered
 * invoices (see {@see Invoice}), not pulled from an external
 * accounting API, so it works regardless of which invoicing tool the agency
 * actually uses.
 */
class BillingBlock extends BlockType
{
    public function type(): string
    {
        return 'billing.summary';
    }

    public function label(): string
    {
        return 'Billing & invoices';
    }

    public function description(): string
    {
        return 'Invoices raised, paid, outstanding and overdue for the period.';
    }

    public function group(): string
    {
        return 'Billing';
    }

    public function availableForSite(Site $site): ?bool
    {
        return $site->client !== null && $site->client->invoices()->exists();
    }

    public function options(): array
    {
        return [
            BlockOption::toggle('compare', 'Compare to previous period', true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $client = $context->site->client;
        if ($client === null) {
            return ['has_data' => false];
        }

        $invoices = $client->invoices()
            ->whereBetween('issued_at', [$context->range->start->toDateString(), $context->range->end->toDateString()])
            ->orderBy('issued_at')
            ->get();

        $compare = (bool) $context->block->configValue('compare', true);
        $previousTotal = null;
        if ($compare && $context->comparison) {
            $previousTotal = (float) $client->invoices()
                ->whereBetween('issued_at', [$context->comparison->start->toDateString(), $context->comparison->end->toDateString()])
                ->sum('amount');
        }

        $totalInvoiced = (float) $invoices->sum('amount');
        $totalPaid = (float) $invoices->where('status', InvoiceStatus::Paid)->sum('amount');
        $totalOutstanding = (float) $invoices->where('status', InvoiceStatus::Sent)->sum('amount');
        $overdueCount = $invoices->filter->isOverdue()->count();
        $currency = $invoices->first()?->currency;

        return [
            'has_data' => $invoices->isNotEmpty(),
            'currency' => $currency,
            'metrics' => [
                ['label' => 'Invoiced', 'fmt' => 'money', 'goodUp' => true, 'current' => $totalInvoiced, 'previous' => $previousTotal],
                ['label' => 'Paid', 'fmt' => 'money', 'goodUp' => true, 'current' => $totalPaid, 'previous' => null],
                ['label' => 'Outstanding', 'fmt' => 'money', 'goodUp' => false, 'current' => $totalOutstanding, 'previous' => null],
                ['label' => 'Overdue', 'fmt' => 'number', 'goodUp' => false, 'current' => (float) $overdueCount, 'previous' => null],
            ],
            'invoices' => $invoices->map(fn ($invoice): array => [
                'number' => $invoice->number,
                'description' => $invoice->description,
                'status' => $invoice->isOverdue() ? 'Overdue' : $invoice->status->label(),
                'issued_at' => $invoice->issued_at->format('d M Y'),
                'amount' => (float) $invoice->amount,
            ])->all(),
            'insight' => $this->insight($invoices->isNotEmpty(), $totalInvoiced, $previousTotal, $currency, $overdueCount),
        ];
    }

    private function insight(bool $hasData, float $total, ?float $previous, ?string $currency, int $overdueCount): ?string
    {
        if (! $hasData) {
            return null;
        }

        $sentence = $previous !== null
            ? Insight::headline('invoiced', $total, $previous, 'money', $currency)
            : Format::money($total, $currency).' invoiced this period.';

        if ($overdueCount > 0) {
            $sentence .= ' '.$overdueCount.' '.($overdueCount === 1 ? 'invoice is' : 'invoices are').' overdue.';
        }

        return $sentence;
    }

    public function icon(): string
    {
        return 'receipt';
    }
}
