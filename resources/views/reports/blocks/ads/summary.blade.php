@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Ad performance', 'icon' => $icon ?? 'chart'])

@if (! ($data['has_data'] ?? false) || empty($data['metrics']))
    <p class="muted">No ad platform data was collected for this period yet.</p>
@else
    @include('reports.blocks.partials.insight', ['insight' => $data['insight'] ?? null])
    @include('reports.blocks.partials.metric-grid', ['metrics' => $data['metrics'], 'currency' => $data['currency'] ?? null])
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
