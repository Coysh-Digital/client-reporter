@php
    use App\Support\Format;
    $countries = $data['countries'] ?? [];
    $total = array_sum(array_map(fn ($c) => (float) ($c['visitors'] ?? 0), $countries));
@endphp
@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Top countries', 'icon' => $icon ?? 'globe', 'suffix' => $data['provider'] ?? null])

@if (empty($countries))
    <p class="muted">No country data for this period.</p>
@else
    <table class="bars">
        <tbody>
            @foreach ($countries as $country)
                @php
                    $visitors = (float) ($country['visitors'] ?? 0);
                    $pct = $total > 0 ? round($visitors / $total * 100) : 0;
                @endphp
                <tr>
                    <td style="width:34%;color:#211f1b;">{{ $country['label'] ?: 'Unknown' }}</td>
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
