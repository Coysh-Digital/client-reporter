@php use App\Support\Format; use App\Support\ReportLang; @endphp
@include('reports.blocks.partials.heading', ['text' => $heading ?: ReportLang::get('cms_status.heading'), 'icon' => $icon ?? 'wrench'])

<div class="table-scroll">
    <table class="data">
        <tbody>
            <tr><th style="width:35%;">{{ ReportLang::get('cms_status.row.wordpress_version') }}</th><td>{{ $data['wordpress_version'] ?? '—' }}</td></tr>
            <tr><th>{{ ReportLang::get('common.php_version') }}</th><td>{{ $data['php_version'] ?? '—' }}</td></tr>
            <tr><th>{{ ReportLang::get('cms_status.row.active_theme') }}</th><td>{{ $data['active_theme'] ?? '—' }}</td></tr>
            @if (! empty($data['site_health']))
                <tr><th>{{ ReportLang::get('cms_status.row.site_health') }}</th><td>{{ ucfirst($data['site_health']) }}</td></tr>
            @endif
            @if ($data['users'] !== null)
                <tr><th>{{ ReportLang::get('cms_status.row.users') }}</th><td>{{ Format::number($data['users']) }}</td></tr>
            @endif
        </tbody>
    </table>
</div>

@if (empty($data['wordpress_version']))
    <p class="muted" style="margin-top:8px;">{{ ReportLang::get('cms_status.empty') }}</p>
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
