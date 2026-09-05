@php
    use App\Support\Format;
    use App\Support\ReportLang;
    use Carbon\CarbonImmutable;

    $incidents = $data['incidents'] ?? [];
@endphp

@include('reports.blocks.partials.heading', ['text' => $heading ?: ReportLang::get('incidents.heading'), 'icon' => $icon ?? 'pulse'])

@if (empty($incidents))
    <p style="color: #3f7d54;">{{ ReportLang::get('incidents.no_outages') }}</p>
@else
    <div class="table-scroll">
        <table class="data">
            <thead>
                <tr>
                    <th>{{ ReportLang::get('common.incidents_col.service') }}</th>
                    <th>{{ ReportLang::get('common.incidents_col.started') }}</th>
                    <th>{{ ReportLang::get('common.incidents_col.duration') }}</th>
                    <th>{{ ReportLang::get('common.incidents_col.reason') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($incidents as $incident)
                    <tr>
                        <td>{{ $incident['monitor'] ?? ReportLang::get('common.monitor_fallback') }}</td>
                        <td>{{ isset($incident['started_at']) ? CarbonImmutable::parse($incident['started_at'])->isoFormat('D MMM, HH:mm') : '—' }}</td>
                        <td>{{ Format::duration($incident['duration_seconds'] ?? 0) }}</td>
                        <td class="muted">{{ $incident['reason'] ?? ReportLang::get('common.incident_reason_down') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
