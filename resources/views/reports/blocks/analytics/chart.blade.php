@include('reports.blocks.partials.heading', ['text' => $heading ?: \App\Support\ReportLang::get('analytics_chart.heading'), 'icon' => $icon ?? 'chart', 'suffix' => $data['provider'] ?? null])

@if (empty($data['timeseries']))
    <p class="muted">{{ \App\Support\ReportLang::get('analytics_chart.empty') }}</p>
@else
    @include('reports.blocks.partials.line-chart', ['series' => $data['timeseries'], 'compareSeries' => $data['timeseries_previous'] ?? [], 'color' => $branding->primaryColor, 'chartHeight' => 130])
@endif

@if ($commentary) <div class="commentary">{!! nl2br(e($commentary)) !!}</div> @endif
