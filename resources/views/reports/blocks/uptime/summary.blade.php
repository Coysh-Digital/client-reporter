@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Uptime & availability', 'icon' => $icon ?? 'pulse'])

@if (! ($data['has_data'] ?? false) || empty($data['metrics']))
    <p class="muted">No uptime data was collected for this period yet.</p>
@else
    @include('reports.blocks.partials.insight', ['insight' => $data['insight'] ?? null])
    @include('reports.blocks.partials.metric-grid', ['metrics' => $data['metrics']])

    @if (! empty($data['timeseries']))
        @include('reports.blocks.partials.line-chart', ['series' => $data['timeseries'], 'color' => $branding->primaryColor, 'chartHeight' => 120, 'zeroBased' => false])
    @endif
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
