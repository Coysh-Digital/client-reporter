@php
    use App\Support\ReportLang;
    $ratingStyle = [
        'good' => 'background:#eaf3ec;color:#3f7d54;',
        'needs-improvement' => 'background:#f7efe1;color:#a4712a;',
        'poor' => 'background:#f6e9e7;color:#a13b32;',
    ];
    $ratingLabel = ['good' => ReportLang::get('performance.rating.good'), 'needs-improvement' => ReportLang::get('performance.rating.needs_work'), 'poor' => ReportLang::get('performance.rating.poor')];
    $sourceLabel = ($data['source'] ?? 'field') === 'field' ? ReportLang::get('performance.source.field') : ReportLang::get('performance.source.lab');
@endphp

@include('reports.blocks.partials.heading', ['text' => $heading ?: ReportLang::get('performance.heading'), 'icon' => $icon ?? 'pulse', 'suffix' => ucfirst($data['strategy'] ?? 'mobile').' · '.$sourceLabel])

@if (! ($data['has_data'] ?? false))
    <p class="muted">{{ ReportLang::get('performance.empty') }}</p>
@else
    @include('reports.blocks.partials.insight', ['insight' => empty($data['ai_summary']) ? ($data['insight'] ?? null) : null])
    @include('reports.blocks.partials.ai-summary', ['aiSummary' => $data['ai_summary'] ?? null])
    <table class="metric-grid" style="width:100%;border-collapse:collapse;margin-top:4px;">
        <tr>
            @if (($data['show_score'] ?? true) && $data['score'] !== null)
                <td style="width:25%;padding:4px 14px 4px 0;vertical-align:top;">
                    <div class="metric-label">{{ ReportLang::get('performance.score_label') }}</div>
                    <div class="metric-value">{{ $data['score'] }}<span style="font-size:15px;color:#98938a;">{{ ReportLang::get('performance.score_suffix') }}</span></div>
                    @if ($data['score_rating'])
                        <span class="pill" style="{{ $ratingStyle[$data['score_rating']] }}margin-top:4px;">{{ $ratingLabel[$data['score_rating']] }}</span>
                    @endif
                </td>
            @endif
            @foreach ($data['vitals'] as $vital)
                <td style="width:25%;padding:4px 14px 4px 0;vertical-align:top;">
                    <div class="metric-label" title="{{ $vital['label'] }}">{{ $vital['key'] }}</div>
                    <div class="metric-value">{{ $vital['value'] }}</div>
                    @if ($vital['rating'])
                        <span class="pill" style="{{ $ratingStyle[$vital['rating']] }}margin-top:4px;">{{ $ratingLabel[$vital['rating']] }}</span>
                    @endif
                </td>
            @endforeach
        </tr>
    </table>

    @if (! empty($data['timeseries']))
        @include('reports.blocks.partials.line-chart', ['series' => $data['timeseries'], 'color' => $branding->primaryColor, 'chartHeight' => 120, 'zeroBased' => false])
    @endif
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
