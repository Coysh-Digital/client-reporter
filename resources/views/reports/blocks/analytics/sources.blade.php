@php
    use App\Support\Format;
    $sources = $data['sources'] ?? [];
    $total = array_sum(array_map(fn ($s) => (float) ($s['visitors'] ?? 0), $sources));
@endphp
@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Where visitors came from', 'icon' => $icon ?? 'chart', 'suffix' => $data['provider'] ?? null])

@if (empty($sources))
    <p class="muted">No source data for this period.</p>
@else
    <table class="bars">
        <tbody>
            @foreach ($sources as $source)
                @php
                    $visitors = (float) ($source['visitors'] ?? 0);
                    $pct = $total > 0 ? round($visitors / $total * 100) : 0;
                @endphp
                <tr>
                    <td style="width:34%;color:#211f1b;">{{ $source['label'] ?: 'Direct' }}</td>
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
