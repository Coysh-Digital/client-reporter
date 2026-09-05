@php use App\Support\Format; use App\Support\ReportLang; @endphp

@include('reports.blocks.partials.heading', ['text' => $heading ?: ReportLang::get('billing.heading'), 'icon' => $icon ?? 'receipt'])

@if (! ($data['has_data'] ?? false) || empty($data['metrics']))
    <p class="muted">{{ ReportLang::get('billing.empty') }}</p>
@else
    @include('reports.blocks.partials.insight', ['insight' => $data['insight'] ?? null])
    @include('reports.blocks.partials.metric-grid', ['metrics' => $data['metrics'], 'currency' => $data['currency'] ?? null])

    @if (! empty($data['invoices']))
        <div class="table-scroll">
            <table class="data" style="margin-top:16px;">
                <thead><tr><th>{{ ReportLang::get('billing.col.invoice') }}</th><th>{{ ReportLang::get('billing.col.status') }}</th><th>{{ ReportLang::get('billing.col.issued') }}</th><th>{{ ReportLang::get('billing.col.amount') }}</th></tr></thead>
                <tbody>
                    @foreach ($data['invoices'] as $invoice)
                        <tr>
                            <td>
                                {{ $invoice['number'] }}
                                @if ($invoice['description'])
                                    <span class="muted">· {{ $invoice['description'] }}</span>
                                @endif
                            </td>
                            <td>{{ $invoice['status'] }}</td>
                            <td>{{ $invoice['issued_at'] }}</td>
                            <td>{{ Format::money($invoice['amount'], $data['currency'] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
