@php use App\Support\Format; @endphp

@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Store performance', 'icon' => $icon ?? 'cart', 'suffix' => $data['provider'] ?? null])

@if (! ($data['active'] ?? false) || empty($data['metrics']))
    <p class="muted">No store was detected on this site for the period.</p>
@else
    @include('reports.blocks.partials.insight', ['insight' => $data['insight'] ?? null])
    @include('reports.blocks.partials.metric-grid', ['metrics' => $data['metrics'], 'currency' => $data['currency'] ?? null])

    @if (! empty($data['timeseries']))
        @include('reports.blocks.partials.chart', ['series' => $data['timeseries']])
    @endif

    @if (! empty($data['top_products']))
        @php
            $maxRevenue = max(1.0, max(array_map(fn ($p) => (float) ($p['revenue'] ?? 0), $data['top_products'])));
        @endphp
        <div class="table-scroll">
            <table class="data" style="margin-top:16px;">
                <thead><tr><th>Top products</th><th style="width:34%;">Revenue</th><th>Sold</th></tr></thead>
                <tbody>
                    @foreach ($data['top_products'] as $product)
                        @php $pct = round((float) ($product['revenue'] ?? 0) / $maxRevenue * 100); @endphp
                        <tr>
                            <td>{{ $product['name'] ?? 'Product' }}</td>
                            <td>
                                <table class="bars"><tr>
                                    <td style="padding:0;"><span class="bar-track"><span class="bar-fill" style="width:{{ $pct }}%;"></span></span></td>
                                    <td style="padding:0 0 0 10px;width:70px;text-align:right;color:#57534a;">{{ isset($product['revenue']) ? Format::money($product['revenue'], $data['currency'] ?? null) : '—' }}</td>
                                </tr></table>
                            </td>
                            <td>{{ Format::number($product['quantity'] ?? 0) }}</td>
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
