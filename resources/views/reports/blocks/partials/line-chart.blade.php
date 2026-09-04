{{--
    A real vector line chart for the PDF (and web). dompdf renders SVG only
    through its image pipeline, so the chart is embedded as an SVG data-URI
    <img>. Expects: $series ([['date'=>'Y-m-d','value'=>n], …]), $color (a #hex
    brand colour — data URIs can't read CSS vars), optional $chartHeight.
--}}
@php
    $series = $series ?? [];
    $color = $color ?? '#33406b';
    $chartHeight = $chartHeight ?? 150;
    $uri = \App\Support\SvgChart::lineDataUri($series, $color, $chartHeight);
    $firstDate = $series[0]['date'] ?? null;
    $lastDate = ! empty($series) ? ($series[count($series) - 1]['date'] ?? null) : null;
@endphp
@if ($uri !== '')
    <img src="{{ $uri }}" alt="" style="display:block;width:100%;height:auto;margin-top:14px;" />
    @if ($firstDate || $lastDate)
        <table style="width:100%;border-collapse:collapse;margin-top:6px;">
            <tr class="muted" style="font-size:11px;">
                <td style="text-align:left;">{{ $firstDate }}</td>
                <td style="text-align:right;">{{ $lastDate }}</td>
            </tr>
        </table>
    @endif
@endif
