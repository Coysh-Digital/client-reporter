{{--
    A real vector line chart for the PDF (and web). dompdf renders SVG only
    through its image pipeline, so the chart is embedded as an SVG data-URI
    <img>. Expects: $series ([['date'=>'Y-m-d','value'=>n], …]), $color (a #hex
    brand colour — data URIs can't read CSS vars), optional $chartHeight, and an
    optional $compareSeries drawn as a dashed "previous period" line.
--}}
@php
    $series = $series ?? [];
    $compareSeries = $compareSeries ?? [];
    $color = $color ?? '#33406b';
    $chartHeight = $chartHeight ?? 150;
    $zeroBased = $zeroBased ?? true;
    $uri = \App\Support\SvgChart::lineDataUri($series, $color, $chartHeight, $zeroBased, $compareSeries);
    $firstDate = $series[0]['date'] ?? null;
    $lastDate = ! empty($series) ? ($series[count($series) - 1]['date'] ?? null) : null;
    $hasCompare = ! empty($compareSeries);
@endphp
@if ($uri !== '')
    <img src="{{ $uri }}" alt="" style="display:block;width:100%;height:auto;margin-top:14px;" />
    @if ($firstDate || $lastDate || $hasCompare)
        <table style="width:100%;border-collapse:collapse;margin-top:6px;">
            <tr class="muted" style="font-size:11px;">
                <td style="text-align:left;">{{ $firstDate }}</td>
                @if ($hasCompare)
                    <td style="text-align:center;white-space:nowrap;">
                        <span style="display:inline-block;width:14px;border-top:2.5px solid {{ $color }};vertical-align:middle;"></span>
                        This period
                        &nbsp;&nbsp;
                        <span style="display:inline-block;width:14px;border-top:1.75px dashed {{ $color }};opacity:0.45;vertical-align:middle;"></span>
                        Previous period
                    </td>
                @endif
                <td style="text-align:right;">{{ $lastDate }}</td>
            </tr>
        </table>
    @endif
@endif
