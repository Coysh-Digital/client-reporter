@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Leads & signups', 'icon' => $icon ?? 'chart', 'suffix' => $data['provider'] ?? null])

@if (! ($data['has_data'] ?? false) || empty($data['metrics']))
    <p class="muted">No leads data was collected for this period yet.</p>
@else
    @include('reports.blocks.partials.insight', ['insight' => $data['insight'] ?? null])
    @include('reports.blocks.partials.ai-summary', ['aiSummary' => $data['ai_summary'] ?? null])
    @include('reports.blocks.partials.metric-grid', ['metrics' => $data['metrics']])
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
