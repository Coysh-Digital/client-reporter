@php
    use App\Support\Format;
    use Carbon\CarbonImmutable;

    $incidents = $data['incidents'] ?? [];
@endphp

@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Incidents', 'icon' => $icon ?? 'pulse'])

@if (empty($incidents))
    <p style="color: #3f7d54;">No outages were recorded during this period. 🎉</p>
@else
    <div class="table-scroll">
        <table class="data">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Started</th>
                    <th>Duration</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($incidents as $incident)
                    <tr>
                        <td>{{ $incident['monitor'] ?? 'Monitor' }}</td>
                        <td>{{ isset($incident['started_at']) ? CarbonImmutable::parse($incident['started_at'])->isoFormat('D MMM, HH:mm') : '—' }}</td>
                        <td>{{ Format::duration($incident['duration_seconds'] ?? 0) }}</td>
                        <td class="muted">{{ $incident['reason'] ?? 'Down' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
