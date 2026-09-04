{{--
    dompdf-safe "area" chart: contiguous bottom-aligned columns (no gaps) filled
    with a translucent brand tint under a solid brand top line, so it reads as a
    filled area silhouette rather than separate bars. No SVG. $series is an array
    of ['date' => 'Y-m-d', 'value' => n].
--}}
@php
    $series = $series ?? [];
    $values = array_map(fn ($p) => (float) ($p['value'] ?? 0), $series);
    $max = max(1.0, $values ? max($values) : 0);
    $chartHeight = $chartHeight ?? 120;
    $firstDate = $series[0]['date'] ?? null;
    $lastDate = ! empty($series) ? ($series[count($series) - 1]['date'] ?? null) : null;
@endphp
@if (! empty($values))
    <table style="width:100%;border-collapse:collapse;height:{{ $chartHeight }}px;table-layout:fixed;margin-top:14px;">
        <tr>
            @foreach ($values as $v)
                @php $h = max(2, (int) round($v / $max * $chartHeight)); @endphp
                <td style="vertical-align:bottom;padding:0;">
                    <div style="height:2px;background:var(--brand-primary);font-size:0;line-height:0;">&nbsp;</div>
                    <div style="height:{{ $h - 2 }}px;background:var(--brand-primary);opacity:0.16;font-size:0;line-height:0;">&nbsp;</div>
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
