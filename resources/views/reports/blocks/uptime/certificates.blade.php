@php
    $ratingStyle = [
        'good' => 'background:#eaf3ec;color:#3f7d54;',
        'needs-improvement' => 'background:#f7efe1;color:#a4712a;',
        'poor' => 'background:#f6e9e7;color:#a13b32;',
    ];
@endphp
@include('reports.blocks.partials.heading', ['text' => $heading ?: 'SSL certificates', 'icon' => $icon ?? 'globe'])

@if (! ($data['has_data'] ?? false))
    <p class="muted">No certificate data was collected for this period yet.</p>
@else
    <div class="table-scroll">
        <table class="data" style="margin-top:4px;">
            <thead><tr><th>Endpoint</th><th>Expires in</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($data['certificates'] as $cert)
                    @php
                        $days = $cert['days'];
                        $label = $days < 0 ? 'Expired' : ($days === 1 ? '1 day' : $days.' days');
                        $status = $cert['rating'] === 'good' ? 'Valid' : ($cert['rating'] === 'needs-improvement' ? 'Renew soon' : ($days < 0 ? 'Expired' : 'Renew now'));
                    @endphp
                    <tr>
                        <td>{{ $cert['host'] ?: '—' }}</td>
                        <td>{{ $label }}</td>
                        <td><span class="pill" style="{{ $ratingStyle[$cert['rating']] }}">{{ $status }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
