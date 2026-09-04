@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Updates', 'icon' => $icon ?? 'wrench'])

@php
    $core = (bool) ($data['core_update'] ?? false);
    $pluginCount = (int) ($data['plugin_updates'] ?? 0);
    $themeCount = (int) ($data['theme_updates'] ?? 0);
    $total = ($core ? 1 : 0) + $pluginCount + $themeCount;

    $parts = [];
    if ($core) $parts[] = 'a core update';
    if ($pluginCount > 0) $parts[] = $pluginCount.' plugin '.($pluginCount === 1 ? 'update' : 'updates');
    if ($themeCount > 0) $parts[] = $themeCount.' theme '.($themeCount === 1 ? 'update' : 'updates');

    $insight = $total === 0
        ? 'Everything is up to date — no pending core, plugin or theme updates.'
        : ucfirst(count($parts) > 1
            ? implode(', ', array_slice($parts, 0, -1)).' and '.end($parts)
            : $parts[0]).' currently pending.';
@endphp

@include('reports.blocks.partials.insight', ['insight' => $insight])

@if ($total > 0)
    <p style="margin-bottom:10px;">
        @if ($data['core_update']) <span class="pill" style="background:#f7efe1;color:#a4712a;">Core update available</span> @endif
        @if (($data['plugin_updates'] ?? 0) > 0) <span class="pill" style="background:#f7efe1;color:#a4712a;">{{ $data['plugin_updates'] }} plugin update{{ $data['plugin_updates'] === 1 ? '' : 's' }}</span> @endif
        @if (($data['theme_updates'] ?? 0) > 0) <span class="pill" style="background:#f7efe1;color:#a4712a;">{{ $data['theme_updates'] }} theme update{{ $data['theme_updates'] === 1 ? '' : 's' }}</span> @endif
    </p>

    @if (! empty($data['plugin_updates_list']))
        <div class="table-scroll">
            <table class="data">
                <thead><tr><th>Plugin</th><th>Installed</th><th>Available</th></tr></thead>
                <tbody>
                    @foreach ($data['plugin_updates_list'] as $plugin)
                        <tr>
                            <td>{{ $plugin['name'] ?? 'Plugin' }}</td>
                            <td class="muted">{{ $plugin['current'] ?? '—' }}</td>
                            <td>{{ $plugin['available'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    <p class="muted" style="margin-top:8px; font-size:13px;">These updates are reported for your awareness. We apply them as part of your care plan.</p>
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
