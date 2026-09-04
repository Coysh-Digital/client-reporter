@php
    use App\Support\Format;
    $events = $data['events'] ?? [];
    $total = array_sum(array_map(fn ($e) => (float) ($e['count'] ?? 0), $events));
@endphp
@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Custom events', 'icon' => $icon ?? 'chart', 'suffix' => $data['provider'] ?? null])

@if (empty($events))
    <p class="muted">No custom events recorded for this analytics property in this period.</p>
@else
    <table class="bars">
        <tbody>
            @foreach ($events as $event)
                @php
                    $count = (float) ($event['count'] ?? 0);
                    $pct = $total > 0 ? round($count / $total * 100) : 0;
                @endphp
                <tr>
                    <td style="width:34%;color:#211f1b;">{{ $event['label'] ?: 'Event' }}</td>
                    <td style="padding-left:14px;padding-right:14px;">
                        <span class="bar-track"><span class="bar-fill" style="width:{{ $pct }}%;"></span></span>
                    </td>
                    <td style="width:70px;text-align:right;color:#57534a;font-variant-numeric:tabular-nums;">{{ Format::number($count) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($commentary) <div class="commentary">{!! nl2br(e($commentary)) !!}</div> @endif
