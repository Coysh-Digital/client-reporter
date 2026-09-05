@php
    use App\Support\Format;
    use App\Support\ReportLang;
    $devices = $data['devices'] ?? [];
    $total = array_sum(array_map(fn ($d) => (float) ($d['visitors'] ?? 0), $devices));
@endphp
@include('reports.blocks.partials.heading', ['text' => $heading ?: ReportLang::get('devices.heading'), 'icon' => $icon ?? 'chart', 'suffix' => $data['provider'] ?? null])

@if (empty($devices))
    <p class="muted">{{ ReportLang::get('devices.empty') }}</p>
@else
    <table class="bars">
        <tbody>
            @foreach ($devices as $device)
                @php
                    $visitors = (float) ($device['visitors'] ?? 0);
                    $pct = $total > 0 ? round($visitors / $total * 100) : 0;
                @endphp
                <tr>
                    <td style="width:34%;color:#211f1b;">{{ $device['label'] ?: ReportLang::get('common.unknown') }}</td>
                    <td style="padding-left:14px;padding-right:14px;">
                        <span class="bar-track"><span class="bar-fill" style="width:{{ $pct }}%;"></span></span>
                    </td>
                    <td style="width:70px;text-align:right;color:#57534a;font-variant-numeric:tabular-nums;">{{ Format::number($visitors) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($commentary) <div class="commentary">{!! nl2br(e($commentary)) !!}</div> @endif
