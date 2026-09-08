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
use App\Support\ReportLang;

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
        return ReportLang::get('billing.heading');
    }

    public function description(): string
    {
        return 'Invoices raised, paid, outstanding and overdue for the period.';
    }

    public function group(): string
    {
        return 'Billing';
    }

    public function canBeEmpty(): bool
    {
        return true;
    }

    /** @var array<int, bool> */
    private array $availability = [];

    /**
     * Only offered when the client has invoices worth showing (drafts don't
     * count). Memoised per site: the builder asks once per block type on every
     * re-render.
     */
    public function availableForSite(Site $site): ?bool
    {
        return $this->availability[$site->id] ??= $site->client !== null
            && $site->client->invoices()->where('status', '!=', InvoiceStatus::Draft)->exists();
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

        // Invoices raised within the reporting period (drafts are never shown).
        $invoices = $client->invoices()
            ->where('status', '!=', InvoiceStatus::Draft)
            ->whereBetween('issued_at', [$context->range->start->toDateString(), $context->range->end->toDateString()])
            ->orderBy('issued_at')
            ->get();

        $compare = (bool) $context->block->configValue('compare', true);
        $previousTotal = null;
        if ($compare && $context->comparison) {
            $previousTotal = (float) $client->invoices()
                ->where('status', '!=', InvoiceStatus::Draft)
                ->whereBetween('issued_at', [$context->comparison->start->toDateString(), $context->comparison->end->toDateString()])
                ->sum('amount');
        }

        // Outstanding and overdue reflect the client's whole current position as
        // of the report date — not just this period — so nothing unpaid is ever
        // hidden because it was raised in an earlier month.
        $unpaid = $client->invoices()->where('status', InvoiceStatus::Sent)->get();
        $totalOutstanding = (float) $unpaid->sum('amount');
        $overdueCount = $unpaid->filter->isOverdue()->count();

        $totalInvoiced = (float) $invoices->sum('amount');
        $totalPaid = (float) $invoices->where('status', InvoiceStatus::Paid)->sum('amount');
        $firstInvoice = $invoices->first() ?? $unpaid->first();
        $currency = $firstInvoice?->currency;

        $hasPeriodInvoices = $invoices->isNotEmpty();
        $hasData = $hasPeriodInvoices || $totalOutstanding > 0.0 || $overdueCount > 0;

        return [
            'has_data' => $hasData,
            'currency' => $currency,
            'metrics' => [
                ['label' => ReportLang::get('billing.metric.invoiced'), 'fmt' => 'money', 'goodUp' => true, 'current' => $totalInvoiced, 'previous' => $previousTotal],
                ['label' => ReportLang::get('billing.metric.paid'), 'fmt' => 'money', 'goodUp' => true, 'current' => $totalPaid, 'previous' => null],
                ['label' => ReportLang::get('billing.metric.outstanding'), 'fmt' => 'money', 'goodUp' => false, 'current' => $totalOutstanding, 'previous' => null],
                ['label' => ReportLang::get('billing.metric.overdue'), 'fmt' => 'number', 'goodUp' => false, 'current' => (float) $overdueCount, 'previous' => null],
            ],
            'invoices' => $invoices->map(fn ($invoice): array => [
                'number' => $invoice->number,
                'description' => $invoice->description,
                'status' => $invoice->isOverdue() ? ReportLang::get('billing.status.overdue') : $invoice->status->label(),
                'issued_at' => $invoice->issued_at->format('d M Y'),
                'amount' => (float) $invoice->amount,
            ])->all(),
            'insight' => $this->insight($hasData, $hasPeriodInvoices, $totalInvoiced, $previousTotal, $totalOutstanding, $currency, $overdueCount),
        ];
    }

    private function insight(bool $hasData, bool $hasPeriodInvoices, float $total, ?float $previous, float $outstanding, ?string $currency, int $overdueCount): ?string
    {
        if (! $hasData) {
            return null;
        }

        if ($hasPeriodInvoices) {
            $sentence = $previous !== null
                ? Insight::headline(ReportLang::get('billing.insight_noun'), $total, $previous, 'money', $currency)
                : ReportLang::get('billing.insight.no_compare', ['total' => Format::money($total, $currency)]);
        } else {
            // Nothing raised this period — lead with the outstanding balance.
            $sentence = ReportLang::get('billing.insight.outstanding_only', ['total' => Format::money($outstanding, $currency)]);
        }

        if ($overdueCount > 0) {
            $sentence .= ReportLang::get(
                $overdueCount === 1 ? 'billing.insight.overdue_singular' : 'billing.insight.overdue_plural',
                ['count' => $overdueCount],
            );
        }

        return $sentence;
    }

    public function icon(): string
    {
        return 'receipt';
    }
}
