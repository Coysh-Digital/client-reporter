@php use App\Support\Format; use App\Support\ReportLang; @endphp

@include('reports.blocks.partials.heading', ['text' => $heading ?: ReportLang::get('downloads.heading'), 'icon' => $icon ?? 'document'])

@if (! ($data['has_data'] ?? false))
    <p class="muted">{{ ReportLang::get('downloads.empty') }}</p>
@else
    @include('reports.blocks.partials.insight', ['insight' => empty($data['ai_summary']) ? ($data['insight'] ?? null) : null])
    @include('reports.blocks.partials.ai-summary', ['aiSummary' => $data['ai_summary'] ?? null])
    @include('reports.blocks.partials.metric-grid', ['metrics' => $data['tiles']])

    @if (! empty($data['timeseries']))
        <div class="mini-bars-title" style="margin-top:22px;">{{ ReportLang::get('downloads.over_time') }}</div>
        @include('reports.blocks.partials.line-chart', ['series' => $data['timeseries'], 'color' => $branding->primaryColor, 'chartHeight' => 120])
    @endif

    @if (! empty($data['top_files']))
        <div class="table-scroll">
            <table class="data" style="margin-top:16px;">
                <thead><tr><th>{{ ReportLang::get('downloads.col.top_files') }}</th><th>{{ ReportLang::get('downloads.col.downloads') }}</th></tr></thead>
                <tbody>
                    @foreach ($data['top_files'] as $file)
                        <tr>
                            <td>{{ $file['label'] ?: '—' }}</td>
                            <td>{{ Format::number($file['downloads'] ?? 0) }}</td>
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
