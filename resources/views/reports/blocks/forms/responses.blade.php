@php
    use App\Support\Format;
    use App\Support\ReportLang;
    $statusColors = [
        'busy' => '#3f7d54',
        'some' => '#8bad97',
        'none' => '#e2dccf',
    ];
    $days = $data['status_days'] ?? [];
@endphp

@include('reports.blocks.partials.heading', ['text' => $heading ?: ReportLang::get('form_responses.heading'), 'icon' => $icon ?? 'chart'])

@if (! ($data['has_data'] ?? false))
    <p class="muted">{{ ReportLang::get('form_responses.empty') }}</p>
@else
    @include('reports.blocks.partials.insight', ['insight' => empty($data['ai_summary']) ? ($data['insight'] ?? null) : null])
    @include('reports.blocks.partials.ai-summary', ['aiSummary' => $data['ai_summary'] ?? null])

    @include('reports.blocks.partials.metric-grid', ['metrics' => $data['tiles']])

    {{-- Responses over time: a daily trend above a per-day activity strip --}}
    @if (! empty($data['timeseries']))
        <div class="mini-bars-title" style="margin-top:24px;">{{ ReportLang::get('form_responses.over_time') }}</div>
        @include('reports.blocks.partials.line-chart', [
            'series' => $data['timeseries'],
            'compareSeries' => $data['timeseries_previous'] ?? [],
            'color' => $branding->primaryColor,
            'chartHeight' => 120,
            'zeroBased' => true,
        ])
        @if (! empty($days))
            <table class="status-strip">
                <tr>
                    @foreach ($days as $day)
                        <td><span class="status-cell" style="background:{{ $statusColors[$day['status']] ?? $statusColors['none'] }};">&nbsp;</span></td>
                    @endforeach
                </tr>
            </table>
            <p style="margin:8px 0 0;font-size:11px;color:#8b857a;">
                @foreach (['busy' => ReportLang::get('form_responses.legend.busy'), 'some' => ReportLang::get('form_responses.legend.some'), 'none' => ReportLang::get('form_responses.legend.none')] as $key => $label)
                    <span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:{{ $statusColors[$key] }};margin:0 4px 0 12px;">&nbsp;</span>{{ $label }}
                @endforeach
            </p>
        @endif
    @endif

    {{-- Busiest forms --}}
    @if (! empty($data['forms']))
        <div class="mini-bars-title" style="margin-top:24px;">{{ ReportLang::get('form_responses.busiest') }}</div>
        <div class="table-scroll">
            <table class="data" style="margin-top:8px;">
                <thead><tr>
                    <th>{{ ReportLang::get('form_responses.col.form') }}</th>
                    <th>{{ ReportLang::get('form_responses.col.source') }}</th>
                    <th style="text-align:right;">{{ ReportLang::get('form_responses.col.responses') }}</th>
                </tr></thead>
                <tbody>
                    @foreach ($data['forms'] as $form)
                        <tr>
                            <td>{{ $form['name'] ?? '—' }}</td>
                            <td>{{ $form['source'] ?? '—' }}</td>
                            <td style="text-align:right;">{{ Format::number($form['submissions'] ?? 0) }}</td>
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
