@php use App\Support\Format; @endphp
@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Site traffic', 'icon' => $icon ?? 'chart', 'suffix' => $data['provider'] ?? null])

@if (! ($data['has_data'] ?? false) || empty($data['tiles']))
    <p class="muted">No analytics data was collected for this period yet.</p>
@else
    @include('reports.blocks.partials.insight', ['insight' => $data['summary'] ?? null])

    {{-- Headline metric tiles --}}
    <table style="width:100%;border-collapse:collapse;table-layout:fixed;margin-top:4px;">
        <tr>
            @foreach ($data['tiles'] as $t)
                @php
                    $cur = $t['current'] ?? null;
                    $prev = $t['previous'] ?? null;
                    $ch = Format::change($cur, $prev);
                    $isGood = in_array($ch['direction'], ['flat', 'none'], true)
                        ? null
                        : (($ch['direction'] === 'up') === ($t['goodUp'] ?? true));
                    $arrow = ['up' => '▲', 'down' => '▼', 'flat' => '■', 'none' => ''][$ch['direction']];
                    $deltaClass = $isGood === null ? 'delta-flat' : ($isGood ? 'delta-up' : 'delta-down');
                @endphp
                <td style="width:25%;padding:0 12px 0 0;vertical-align:top;">
                    <div style="border:1px solid #efe8da;border-radius:8px;padding:13px 15px;">
                        <div class="metric-label">{{ $t['label'] }}</div>
                        <div class="metric-value" style="font-size:23px;margin-top:5px;">{{ Format::forType($cur, $t['fmt'] ?? 'number') }}</div>
                        @if ($prev !== null && $ch['percent'] !== null)
                            <div class="delta {{ $deltaClass }}" style="margin-top:4px;">{{ $arrow }} {{ Format::number(abs($ch['percent']), 1) }}%</div>
                        @endif
                    </div>
                </td>
            @endforeach
        </tr>
    </table>

    @if (($data['bounce_rate'] ?? null) !== null)
        <p class="muted" style="font-size:13px;margin:12px 0 0;">Avg bounce rate {{ Format::percent($data['bounce_rate'], 1) }}</p>
    @endif

    {{-- Visitors over time --}}
    @if (! empty($data['timeseries']))
        <div class="mini-bars-title" style="margin-top:24px;">Visitors over time</div>
        @include('reports.blocks.partials.chart', ['series' => $data['timeseries'], 'chartHeight' => 130])
    @endif

    {{-- Top pages / referrers / countries / devices --}}
    <table style="width:100%;border-collapse:collapse;table-layout:fixed;margin-top:26px;">
        <tr>
            <td style="width:25%;padding-right:18px;vertical-align:top;">
                @include('reports.blocks.partials.mini-bars', [
                    'title' => 'Top pages',
                    'unit' => 'pv',
                    'items' => array_map(fn ($p) => ['label' => $p['label'] ?? '', 'value' => $p['pageviews'] ?? 0], $data['top_pages'] ?? []),
                ])
            </td>
            <td style="width:25%;padding-right:18px;vertical-align:top;">
                @include('reports.blocks.partials.mini-bars', [
                    'title' => 'Top referrers',
                    'items' => array_map(fn ($s) => ['label' => $s['label'] ?? '', 'value' => $s['visitors'] ?? 0], $data['sources'] ?? []),
                ])
            </td>
            <td style="width:25%;padding-right:18px;vertical-align:top;">
                @include('reports.blocks.partials.mini-bars', [
                    'title' => 'Top countries',
                    'items' => array_map(fn ($c) => ['label' => $c['label'] ?? '', 'value' => $c['visitors'] ?? 0], $data['countries'] ?? []),
                ])
            </td>
            <td style="width:25%;vertical-align:top;">
                @include('reports.blocks.partials.mini-bars', [
                    'title' => 'Top devices',
                    'items' => array_map(fn ($d) => ['label' => $d['label'] ?? '', 'value' => $d['visitors'] ?? 0], $data['devices'] ?? []),
                ])
            </td>
        </tr>
    </table>

    @if (empty($data['events']))
        <p class="muted" style="font-size:12.5px;margin-top:20px;">No custom events recorded for this analytics property in this period.</p>
    @endif
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
