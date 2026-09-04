@php use App\Support\Format; @endphp

@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Search performance', 'icon' => $icon ?? 'search', 'suffix' => 'Google Search Console'])

@if (! ($data['has_data'] ?? false) || empty($data['metrics']))
    <p class="muted">No search data was collected for this period yet.</p>
@else
    @include('reports.blocks.partials.insight', ['insight' => $data['insight'] ?? null])
    @include('reports.blocks.partials.metric-grid', ['metrics' => $data['metrics']])

    @if (! empty($data['queries']))
        <div class="table-scroll">
            <table class="data" style="margin-top:16px;">
                <thead><tr><th>Top queries</th><th>Clicks</th><th>Impressions</th><th>Position</th></tr></thead>
                <tbody>
                    @foreach ($data['queries'] as $query)
                        <tr>
                            <td>{{ $query['label'] ?: '—' }}</td>
                            <td>{{ Format::number($query['clicks'] ?? 0) }}</td>
                            <td>{{ Format::number($query['impressions'] ?? 0) }}</td>
                            <td>{{ Format::number($query['position'] ?? 0, 1) }}</td>
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
