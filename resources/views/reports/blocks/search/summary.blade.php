@php use App\Support\Format; @endphp

@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Search performance', 'icon' => $icon ?? 'search', 'suffix' => 'Google Search Console'])

@if (! ($data['has_data'] ?? false) || empty($data['metrics']))
    <p class="muted">No search data was collected for this period yet.</p>
@else
    @include('reports.blocks.partials.insight', ['insight' => $data['insight'] ?? null])
    @include('reports.blocks.partials.ai-summary', ['aiSummary' => $data['ai_summary'] ?? null])
    @include('reports.blocks.partials.metric-grid', ['metrics' => $data['metrics']])

    @if (! empty($data['timeseries']))
        <div class="mini-bars-title" style="margin-top:22px;">Search clicks over time</div>
        @include('reports.blocks.partials.line-chart', ['series' => $data['timeseries'], 'color' => $branding->primaryColor, 'chartHeight' => 120])
    @endif

    @if (! empty($data['queries']))
        <div class="table-scroll">
            <table class="data" style="margin-top:16px;">
                <thead><tr><th>Top queries</th><th>Clicks</th><th>Impressions</th><th>CTR</th><th>Position</th></tr></thead>
                <tbody>
                    @foreach ($data['queries'] as $query)
                        <tr>
                            <td>{{ $query['label'] ?: '—' }}</td>
                            <td>{{ Format::number($query['clicks'] ?? 0) }}</td>
                            <td>{{ Format::number($query['impressions'] ?? 0) }}</td>
                            <td>{{ Format::percent($query['ctr'] ?? 0, 1) }}</td>
                            <td>{{ Format::number($query['position'] ?? 0, 1) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if (! empty($data['pages']))
        <div class="table-scroll">
            <table class="data" style="margin-top:16px;">
                <thead><tr><th>Top landing pages</th><th>Clicks</th><th>Impressions</th><th>CTR</th><th>Position</th></tr></thead>
                <tbody>
                    @foreach ($data['pages'] as $page)
                        <tr>
                            <td>{{ parse_url($page['label'] ?? '', PHP_URL_PATH) ?: ($page['label'] ?: '—') }}</td>
                            <td>{{ Format::number($page['clicks'] ?? 0) }}</td>
                            <td>{{ Format::number($page['impressions'] ?? 0) }}</td>
                            <td>{{ Format::percent($page['ctr'] ?? 0, 1) }}</td>
                            <td>{{ Format::number($page['position'] ?? 0, 1) }}</td>
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
