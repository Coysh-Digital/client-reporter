@php use App\Support\Format; use App\Support\ReportLang; @endphp

@include('reports.blocks.partials.heading', ['text' => $heading ?: ReportLang::get('forms.heading'), 'icon' => $icon ?? 'envelope'])

@if (! ($data['has_data'] ?? false))
    <p class="muted">{{ ReportLang::get('forms.empty') }}</p>
@else
    @include('reports.blocks.partials.insight', ['insight' => empty($data['ai_summary']) ? ($data['insight'] ?? null) : null])
    @include('reports.blocks.partials.ai-summary', ['aiSummary' => $data['ai_summary'] ?? null])
    @include('reports.blocks.partials.metric-grid', ['metrics' => $data['metrics']])

    @if (! empty($data['timeseries']))
        @include('reports.blocks.partials.chart', ['series' => $data['timeseries']])
    @endif

    @if (! empty($data['forms']))
        @php
            $maxSubs = max(1.0, max(array_map(fn ($f) => (float) ($f['submissions'] ?? 0), $data['forms'])));
        @endphp
        <div class="table-scroll">
            <table class="data" style="margin-top:16px;">
                <thead><tr><th>{{ ReportLang::get('forms.col.form') }}</th><th>{{ ReportLang::get('forms.col.source') }}</th><th style="width:34%;">{{ ReportLang::get('forms.col.submissions') }}</th></tr></thead>
                <tbody>
                    @foreach ($data['forms'] as $form)
                        @php $pct = round((float) ($form['submissions'] ?? 0) / $maxSubs * 100); @endphp
                        <tr>
                            <td>{{ $form['name'] ?? ReportLang::get('forms.form_fallback') }}</td>
                            <td class="muted">{{ $form['source'] ?? '—' }}</td>
                            <td>
                                <table class="bars"><tr>
                                    <td style="padding:0;"><span class="bar-track"><span class="bar-fill" style="width:{{ $pct }}%;"></span></span></td>
                                    <td style="padding:0 0 0 10px;width:56px;text-align:right;color:#57534a;">{{ Format::number($form['submissions'] ?? 0) }}</td>
                                </tr></table>
                            </td>
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
