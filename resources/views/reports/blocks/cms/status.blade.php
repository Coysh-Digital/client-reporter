@php use App\Support\Format; @endphp
@include('reports.blocks.partials.heading', ['text' => $heading ?: 'CMS status', 'icon' => $icon ?? 'wrench'])

<div class="table-scroll">
    <table class="data">
        <tbody>
            <tr><th style="width:35%;">WordPress version</th><td>{{ $data['wordpress_version'] ?? '—' }}</td></tr>
            <tr><th>PHP version</th><td>{{ $data['php_version'] ?? '—' }}</td></tr>
            <tr><th>Active theme</th><td>{{ $data['active_theme'] ?? '—' }}</td></tr>
            @if (! empty($data['site_health']))
                <tr><th>Site Health</th><td>{{ ucfirst($data['site_health']) }}</td></tr>
            @endif
            @if ($data['users'] !== null)
                <tr><th>Users</th><td>{{ Format::number($data['users']) }}</td></tr>
            @endif
        </tbody>
    </table>
</div>

@if (empty($data['wordpress_version']))
    <p class="muted" style="margin-top:8px;">No CMS data has been collected yet.</p>
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
