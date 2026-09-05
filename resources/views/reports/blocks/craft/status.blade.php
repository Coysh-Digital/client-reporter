@php use App\Support\ReportLang; @endphp
@include('reports.blocks.partials.heading', ['text' => $heading ?: ReportLang::get('craft_status.heading'), 'icon' => $icon ?? 'wrench'])

<div class="table-scroll">
    <table class="data">
        <tbody>
            <tr><th style="width:35%;">{{ ReportLang::get('craft_status.row.craft_version') }}</th><td>{{ $data['craft_version'] ?? '—' }}</td></tr>
            <tr><th>{{ ReportLang::get('common.php_version') }}</th><td>{{ $data['php_version'] ?? '—' }}</td></tr>
            @if (! empty($data['environment']))
                <tr><th>{{ ReportLang::get('craft_status.row.environment') }}</th><td>{{ ucfirst($data['environment']) }}</td></tr>
            @endif
            <tr><th>{{ ReportLang::get('craft_status.row.queue') }}</th><td>
                @if (($data['queue_failed'] ?? 0) > 0)
                    <span style="color:#a13b32;">{{ ReportLang::get('craft_status.queue.failed', ['count' => $data['queue_failed']]) }}</span>
                @elseif (($data['queue_pending'] ?? 0) > 0)
                    {{ ReportLang::get('craft_status.queue.pending', ['count' => $data['queue_pending']]) }}
                @else
                    <span style="color:#3f7d54;">{{ ReportLang::get('craft_status.queue.healthy') }}</span>
                @endif
            </td></tr>
            @if (! empty($data['licence']))
                <tr><th>{{ ReportLang::get('craft_status.row.licence') }}</th><td>{{ ucfirst($data['licence']) }}</td></tr>
            @endif
        </tbody>
    </table>
</div>

@if (empty($data['craft_version']))
    <p class="muted" style="margin-top:8px;">{{ ReportLang::get('craft_status.empty') }}</p>
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
