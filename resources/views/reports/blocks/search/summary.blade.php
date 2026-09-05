@php use App\Support\Format; use App\Support\ReportLang; @endphp

@include('reports.blocks.partials.heading', ['text' => $heading ?: ReportLang::get('search.heading'), 'icon' => $icon ?? 'search', 'suffix' => ReportLang::get('search.source')])

@if (! ($data['has_data'] ?? false) || empty($data['metrics']))
    <p class="muted">{{ ReportLang::get('search.empty') }}</p>
@else
    @include('reports.blocks.partials.insight', ['insight' => empty($data['ai_summary']) ? ($data['insight'] ?? null) : null])
    @include('reports.blocks.partials.ai-summary', ['aiSummary' => $data['ai_summary'] ?? null])
    @include('reports.blocks.partials.metric-grid', ['metrics' => $data['metrics']])

    @if (! empty($data['timeseries']))
        <div class="mini-bars-title" style="margin-top:22px;">{{ ReportLang::get('search.clicks_over_time') }}</div>
        @include('reports.blocks.partials.line-chart', ['series' => $data['timeseries'], 'color' => $branding->primaryColor, 'chartHeight' => 120])
    @endif

    @if (! empty($data['queries']))
        <div class="table-scroll">
            <table class="data" style="margin-top:16px;">
                <thead><tr><th>{{ ReportLang::get('search.queries.col.queries') }}</th><th>{{ ReportLang::get('search.queries.col.clicks') }}</th><th>{{ ReportLang::get('search.queries.col.impressions') }}</th><th>{{ ReportLang::get('search.queries.col.ctr') }}</th><th>{{ ReportLang::get('search.queries.col.position') }}</th></tr></thead>
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
                <thead><tr><th>{{ ReportLang::get('search.pages.col.pages') }}</th><th>{{ ReportLang::get('search.pages.col.clicks') }}</th><th>{{ ReportLang::get('search.pages.col.impressions') }}</th><th>{{ ReportLang::get('search.pages.col.ctr') }}</th><th>{{ ReportLang::get('search.pages.col.position') }}</th></tr></thead>
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
