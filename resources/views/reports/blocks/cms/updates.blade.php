@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Updates', 'icon' => $icon ?? 'wrench'])

@php
    $core = (bool) ($data['core_update'] ?? false);
    $pluginCount = (int) ($data['plugin_updates'] ?? 0);
    $themeCount = (int) ($data['theme_updates'] ?? 0);
    $total = ($core ? 1 : 0) + $pluginCount + $themeCount;

    $applied = $data['applied'] ?? [];
    $appliedTotal = (int) ($data['applied_total'] ?? 0);

    // Lead with the maintenance story: what we applied this period.
    if ($appliedTotal > 0) {
        $insight = 'We applied '.$appliedTotal.' '.($appliedTotal === 1 ? 'update' : 'updates').' this period to keep the site current and secure.';
    } elseif ($total === 0) {
        $insight = 'No updates were needed this period — the site is fully up to date.';
    } else {
        $insight = 'No updates were applied this period. '.$total.' '.($total === 1 ? 'update is' : 'updates are').' currently pending.';
    }
@endphp

@include('reports.blocks.partials.insight', ['insight' => $insight])

@if ($appliedTotal > 0)
    <div class="mini-bars-title" style="margin-top:18px;">Applied this period</div>
    <div class="table-scroll">
        <table class="data" style="margin-top:8px;">
            <thead><tr><th>Item</th><th>Type</th><th>Version</th><th>Date</th></tr></thead>
            <tbody>
                @foreach ($applied as $item)
                    <tr>
                        <td>{{ $item['name'] ?? '—' }}</td>
                        <td class="muted">{{ ucfirst($item['type'] ?? '') }}</td>
                        <td class="muted">{{ $item['version'] ?? '—' }}</td>
                        <td class="muted">{{ ! empty($item['date']) ? \Illuminate\Support\Carbon::parse($item['date'])->format('j M Y') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($total > 0)
    <div class="mini-bars-title" style="margin-top:18px;">Currently pending</div>
@endif

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
