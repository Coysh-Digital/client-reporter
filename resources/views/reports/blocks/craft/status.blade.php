@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Craft status', 'icon' => $icon ?? 'wrench'])

<div class="table-scroll">
    <table class="data">
        <tbody>
            <tr><th style="width:35%;">Craft version</th><td>{{ $data['craft_version'] ?? '—' }}</td></tr>
            <tr><th>PHP version</th><td>{{ $data['php_version'] ?? '—' }}</td></tr>
            @if (! empty($data['environment']))
                <tr><th>Environment</th><td>{{ ucfirst($data['environment']) }}</td></tr>
            @endif
            <tr><th>Queue</th><td>
                @if (($data['queue_failed'] ?? 0) > 0)
                    <span style="color:#a13b32;">{{ $data['queue_failed'] }} failed</span>
                @elseif (($data['queue_pending'] ?? 0) > 0)
                    {{ $data['queue_pending'] }} pending
                @else
                    <span style="color:#3f7d54;">Healthy</span>
                @endif
            </td></tr>
            @if (! empty($data['licence']))
                <tr><th>Licence</th><td>{{ ucfirst($data['licence']) }}</td></tr>
            @endif
        </tbody>
    </table>
</div>

@if (empty($data['craft_version']))
    <p class="muted" style="margin-top:8px;">No Craft data has been collected yet.</p>
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
