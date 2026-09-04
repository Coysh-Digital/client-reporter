@php use App\Support\Format; @endphp

@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Billing & invoices', 'icon' => $icon ?? 'receipt'])

@if (! ($data['has_data'] ?? false) || empty($data['metrics']))
    <p class="muted">No invoices were raised for this period.</p>
@else
    @include('reports.blocks.partials.insight', ['insight' => $data['insight'] ?? null])
    @include('reports.blocks.partials.metric-grid', ['metrics' => $data['metrics'], 'currency' => $data['currency'] ?? null])

    @if (! empty($data['invoices']))
        <div class="table-scroll">
            <table class="data" style="margin-top:16px;">
                <thead><tr><th>Invoice</th><th>Status</th><th>Issued</th><th>Amount</th></tr></thead>
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
