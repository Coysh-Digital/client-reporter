@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Visitors per day', 'icon' => $icon ?? 'chart', 'suffix' => $data['provider'] ?? null])

@if (empty($data['timeseries']))
    <p class="muted">No daily data for this period.</p>
@else
    @include('reports.blocks.partials.line-chart', ['series' => $data['timeseries'], 'color' => $branding->primaryColor, 'chartHeight' => 130])
@endif

@if ($commentary) <div class="commentary">{!! nl2br(e($commentary)) !!}</div> @endif
