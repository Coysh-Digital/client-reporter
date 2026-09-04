@php
    use App\Support\Format;
    $statusColors = [
        'healthy' => '#3f7d54',
        'partial' => '#a4712a',
        'below' => '#a13b32',
        'none' => '#e2dccf',
    ];
    $ratingColors = ['good' => '#3f7d54', 'needs-improvement' => '#a4712a', 'poor' => '#a13b32'];
    $days = $data['status_days'] ?? [];
@endphp
@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Uptime & performance', 'icon' => $icon ?? 'pulse'])

@if (! ($data['has_data'] ?? false) || empty($data['tiles']))
    <p class="muted">No uptime data was collected for this period yet.</p>
@else
    @include('reports.blocks.partials.insight', ['insight' => $data['summary'] ?? null])

    {{-- Headline tiles --}}
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
                <td style="width:20%;padding:0 10px 0 0;vertical-align:top;">
                    <div style="border:1px solid #efe8da;border-radius:8px;padding:12px 13px;">
                        <div class="metric-label">{{ $t['label'] }}</div>
                        <div class="metric-value" style="font-size:21px;margin-top:5px;">{{ Format::forType($cur, $t['fmt'] ?? 'number') }}</div>
                        @if ($prev !== null && $ch['percent'] !== null)
                            <div class="delta {{ $deltaClass }}" style="margin-top:4px;">{{ $arrow }} {{ Format::number(abs($ch['percent']), 1) }}%</div>
                        @endif
                    </div>
                </td>
            @endforeach
        </tr>
    </table>

    {{-- Daily uptime strip --}}
    @if (! empty($days))
        <div class="mini-bars-title" style="margin-top:24px;">Daily uptime</div>
        <table class="status-strip">
            <tr>
                @foreach ($days as $day)
                    <td><span class="status-cell" style="background:{{ $statusColors[$day['status']] ?? $statusColors['none'] }};">&nbsp;</span></td>
                @endforeach
            </tr>
        </table>
        <table style="width:100%;border-collapse:collapse;margin-top:8px;">
            <tr class="muted" style="font-size:11px;">
                <td style="text-align:left;">{{ $days[0]['date'] ?? '' }}</td>
                <td style="text-align:right;">{{ $days[count($days) - 1]['date'] ?? '' }}</td>
            </tr>
        </table>
        <p style="margin:8px 0 0;font-size:11px;color:#8b857a;">
            @foreach (['healthy' => 'Healthy', 'partial' => 'Partial', 'below' => 'Below 99.5%', 'none' => 'No check'] as $key => $label)
                <span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:{{ $statusColors[$key] }};margin:0 4px 0 12px;">&nbsp;</span>{{ $label }}
            @endforeach
        </p>
    @endif

    {{-- Lighthouse performance --}}
    @if (($data['performance_score'] ?? null) !== null)
        @php $rc = $ratingColors[$data['performance_rating']] ?? '#8b857a'; @endphp
        <div class="mini-bars-title" style="margin-top:26px;">Lighthouse</div>
        <table style="border-collapse:collapse;margin-top:10px;"><tr>
            <td style="vertical-align:middle;">
                <span class="gauge-ring" style="border-color:{{ $rc }};"><span class="gauge-value" style="color:{{ $rc }};">{{ $data['performance_score'] }}</span></span>
            </td>
            <td style="vertical-align:middle;padding-left:16px;">
                <div class="metric-label">Performance</div>
                <div class="muted" style="font-size:12.5px;margin-top:2px;">Google Lighthouse score (0–100)</div>
            </td>
        </tr></table>
    @endif

    {{-- Incidents --}}
    <div class="mini-bars-title" style="margin-top:26px;">Incidents this period</div>
    @if (empty($data['incidents']))
        <p style="margin:8px 0 0;font-size:13.5px;color:#3f7d54;">No outages detected during this period. 🎉</p>
    @else
        <table class="data">
            <thead><tr><th>Service</th><th>Started</th><th style="text-align:right;">Duration</th><th>Reason</th></tr></thead>
            <tbody>
                @foreach ($data['incidents'] as $incident)
                    <tr>
                        <td>{{ $incident['monitor'] ?? '—' }}</td>
                        <td>{{ isset($incident['started_at']) ? \Illuminate\Support\Carbon::parse($incident['started_at'])->format('d M, H:i') : '—' }}</td>
                        <td style="text-align:right;">{{ Format::duration($incident['duration_seconds'] ?? 0) }}</td>
                        <td>{{ $incident['reason'] ?? 'Down' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
