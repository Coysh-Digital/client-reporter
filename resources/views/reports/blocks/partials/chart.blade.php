{{--
    Shared dompdf-safe daily bar chart: a table of bottom-aligned cells with
    fixed-height fills. $series is an array of ['date' => 'Y-m-d', 'value' => n].
--}}
@php
    $series = $series ?? [];
    $values = array_map(fn ($p) => (float) ($p['value'] ?? 0), $series);
    $max = $values ? max($values) : 0;
    $max = max(1.0, $max);
    $peakIndex = $values ? array_search($max, $values, true) : null;
    $chartHeight = $chartHeight ?? 80;
    $firstDate = $series[0]['date'] ?? null;
    $lastDate = ! empty($series) ? ($series[count($series) - 1]['date'] ?? null) : null;
@endphp
@if (! empty($values))
    <table style="width:100%;border-collapse:collapse;height:{{ $chartHeight }}px;table-layout:fixed;margin-top:14px;">
        <tr>
            @foreach ($values as $i => $v)
                @php $h = max(1, (int) round($v / $max * $chartHeight)); @endphp
                <td style="vertical-align:bottom;padding:0 1px;">
                    <div style="height:{{ $h }}px;background:{{ $i === $peakIndex ? 'var(--brand-primary)' : '#c8cce0' }};border-radius:2px 2px 0 0;font-size:0;line-height:0;">&nbsp;</div>
                </td>
            @endforeach
        </tr>
    </table>
    @if ($firstDate || $lastDate)
        <table style="width:100%;border-collapse:collapse;margin-top:8px;">
            <tr class="muted" style="font-size:11px;">
                <td style="text-align:left;">{{ $firstDate }}</td>
                <td style="text-align:right;">{{ $lastDate }}</td>
            </tr>
        </table>
    @endif
@endif
