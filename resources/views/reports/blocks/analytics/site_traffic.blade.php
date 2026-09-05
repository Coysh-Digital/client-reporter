@php use App\Support\Format; use App\Support\ReportLang; @endphp
@include('reports.blocks.partials.heading', ['text' => $heading ?: ReportLang::get('traffic.heading'), 'icon' => $icon ?? 'chart', 'suffix' => $data['provider'] ?? null])

@if (! ($data['has_data'] ?? false) || empty($data['tiles']))
    <p class="muted">{{ ReportLang::get('common.empty.analytics') }}</p>
@else
    @include('reports.blocks.partials.insight', ['insight' => empty($data['ai_summary']) ? ($data['summary'] ?? null) : null])
    @include('reports.blocks.partials.ai-summary', ['aiSummary' => $data['ai_summary'] ?? null])

    {{-- Headline metric tiles --}}
    <table class="tile-grid" style="width:100%;border-collapse:collapse;table-layout:fixed;margin-top:4px;">
        <tr>
            @foreach ($data['tiles'] as $t)
                @php
                    $cur = $t['current'] ?? null;
                    $prev = $t['previous'] ?? null;
                    $ch = Format::change($cur, $prev);
                    $isGood = in_array($ch['direction'], ['flat', 'none'], true)
                        ? null
                        : (($ch['direction'] === 'up') === ($t['goodUp'] ?? true));
                    $arrow = ['up' => '+', 'down' => '-', 'flat' => '', 'none' => ''][$ch['direction']];
                    $deltaClass = $isGood === null ? 'delta-flat' : ($isGood ? 'delta-up' : 'delta-down');
                @endphp
                <td style="width:25%;padding:0 12px 0 0;vertical-align:top;">
                    <div class="metric-tile">
                        <div class="metric-label">{{ $t['label'] }}</div>
                        <div class="metric-value" style="font-size:23px;margin-top:5px;">{{ Format::forType($cur, $t['fmt'] ?? 'number') }}</div>
                        @if ($prev !== null && $ch['percent'] !== null)
                            <div class="delta {{ $deltaClass }}" style="margin-top:4px;">{{ $arrow }}{{ Format::number(abs($ch['percent']), 1) }}%</div>
                        @endif
                    </div>
                </td>
            @endforeach
        </tr>
    </table>

    @if (($data['bounce_rate'] ?? null) !== null)
        <p class="muted" style="font-size:13px;margin:12px 0 0;">{{ ReportLang::get('traffic.bounce_rate_label', ['rate' => Format::percent($data['bounce_rate'], 1)]) }}</p>
    @endif

    {{-- Visitors over time --}}
    @if (! empty($data['timeseries']))
        <div class="mini-bars-title" style="margin-top:24px;">{{ ReportLang::get('traffic.visitors_over_time') }}</div>
        @include('reports.blocks.partials.line-chart', ['series' => $data['timeseries'], 'compareSeries' => $data['timeseries_previous'] ?? [], 'color' => $branding->primaryColor, 'chartHeight' => 150])
    @endif

    {{-- Top pages / referrers / countries / devices --}}
    <table class="tile-grid" style="width:100%;border-collapse:collapse;table-layout:fixed;margin-top:26px;">
        <tr>
            <td style="width:25%;padding-right:18px;vertical-align:top;">
                @include('reports.blocks.partials.mini-bars', [
                    'title' => ReportLang::get('traffic.mini.top_pages'),
                    'unit' => ReportLang::get('traffic.mini.unit_pageviews'),
                    'items' => array_map(fn ($p) => ['label' => $p['label'] ?? '', 'value' => $p['pageviews'] ?? 0], $data['top_pages'] ?? []),
                ])
            </td>
            <td style="width:25%;padding-right:18px;vertical-align:top;">
                @include('reports.blocks.partials.mini-bars', [
                    'title' => ReportLang::get('traffic.mini.top_referrers'),
                    'items' => array_map(fn ($s) => ['label' => $s['label'] ?? '', 'value' => $s['visitors'] ?? 0], $data['sources'] ?? []),
                ])
            </td>
            <td style="width:25%;padding-right:18px;vertical-align:top;">
                @include('reports.blocks.partials.mini-bars', [
                    'title' => ReportLang::get('traffic.mini.top_countries'),
                    'items' => array_map(fn ($c) => ['label' => $c['label'] ?? '', 'value' => $c['visitors'] ?? 0], $data['countries'] ?? []),
                ])
            </td>
            <td style="width:25%;vertical-align:top;">
                @include('reports.blocks.partials.mini-bars', [
                    'title' => ReportLang::get('traffic.mini.top_devices'),
                    'items' => array_map(fn ($d) => ['label' => $d['label'] ?? '', 'value' => $d['visitors'] ?? 0], $data['devices'] ?? []),
                ])
            </td>
        </tr>
    </table>

    @if (empty($data['events']))
        <p class="muted" style="font-size:12.5px;margin-top:20px;">{{ ReportLang::get('common.empty.events') }}</p>
    @endif
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
