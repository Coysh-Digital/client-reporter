{{--
    A compact labelled horizontal-bar list for a report column (top pages,
    referrers, countries, devices). dompdf-safe: plain table + div fills, no SVG.
    Expects: $title, $items (each ['label','value']), optional $unit.
--}}
@php
    use App\Support\Format;
    $items = $items ?? [];
    $unit = $unit ?? '';
    $total = array_sum(array_map(fn ($i) => (float) ($i['value'] ?? 0), $items));
@endphp
<div class="mini-bars-title">{{ $title }}</div>
@if (empty($items))
    <p class="muted" style="font-size:12px;margin:6px 0 0;">No data</p>
@else
    <table style="width:100%;border-collapse:collapse;margin-top:8px;">
        @foreach ($items as $i)
            @php
                $v = (float) ($i['value'] ?? 0);
                $pct = $total > 0 ? round($v / $total * 100) : 0;
            @endphp
            <tr>
                <td style="padding:0;font-size:12.5px;color:#211f1b;">
                    {{ $i['label'] !== '' ? $i['label'] : 'Direct' }}
                    <span style="float:right;color:#57534a;font-variant-numeric:tabular-nums;">{{ Format::number($v) }}{{ $unit ? ' '.$unit : '' }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding:4px 0 12px;">
                    <span style="display:block;height:5px;background:#eee7d9;border-radius:3px;overflow:hidden;">
                        <span style="display:block;height:5px;width:{{ $pct }}%;background:var(--brand-primary);border-radius:3px;font-size:0;line-height:0;">&nbsp;</span>
                    </span>
                </td>
            </tr>
        @endforeach
    </table>
@endif
