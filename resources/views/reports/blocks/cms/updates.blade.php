@include('reports.blocks.partials.heading', ['text' => $heading ?: \App\Support\ReportLang::get('cms_updates.heading'), 'icon' => $icon ?? 'wrench'])

@php
    use App\Support\ReportLang;
    $core = (bool) ($data['core_update'] ?? false);
    $pluginCount = (int) ($data['plugin_updates'] ?? 0);
    $themeCount = (int) ($data['theme_updates'] ?? 0);
    $total = ($core ? 1 : 0) + $pluginCount + $themeCount;

    $applied = $data['applied'] ?? [];
    $appliedTotal = (int) ($data['applied_total'] ?? 0);

    // Lead with the maintenance story: what we applied this period.
    if ($appliedTotal > 0) {
        $insight = ReportLang::get($appliedTotal === 1 ? 'cms_updates.insight.applied_singular' : 'cms_updates.insight.applied_plural', ['count' => $appliedTotal]);
    } elseif ($total === 0) {
        $insight = ReportLang::get('cms_updates.insight.none');
    } else {
        $insight = ReportLang::get($total === 1 ? 'cms_updates.insight.pending_singular' : 'cms_updates.insight.pending_plural', ['count' => $total]);
    }
@endphp

@include('reports.blocks.partials.insight', ['insight' => $insight])

@if ($appliedTotal > 0)
    <div class="mini-bars-title" style="margin-top:18px;">{{ ReportLang::get('cms_updates.applied_heading') }}</div>
    <div class="table-scroll">
        <table class="data" style="margin-top:8px;">
            <thead><tr><th>{{ ReportLang::get('cms_updates.applied.col.item') }}</th><th>{{ ReportLang::get('cms_updates.applied.col.type') }}</th><th>{{ ReportLang::get('cms_updates.applied.col.version') }}</th><th>{{ ReportLang::get('cms_updates.applied.col.date') }}</th></tr></thead>
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
    <div class="mini-bars-title" style="margin-top:18px;">{{ ReportLang::get('cms_updates.pending_heading') }}</div>
@endif

@if ($total > 0)
    <p style="margin-bottom:10px;">
        @if ($data['core_update']) <span class="pill" style="background:#f7efe1;color:#a4712a;">{{ ReportLang::get('cms_updates.pill.core') }}</span> @endif
        @if (($data['plugin_updates'] ?? 0) > 0) <span class="pill" style="background:#f7efe1;color:#a4712a;">{{ ReportLang::get($data['plugin_updates'] === 1 ? 'cms_updates.pill.plugins_singular' : 'cms_updates.pill.plugins_plural', ['count' => $data['plugin_updates']]) }}</span> @endif
        @if (($data['theme_updates'] ?? 0) > 0) <span class="pill" style="background:#f7efe1;color:#a4712a;">{{ ReportLang::get($data['theme_updates'] === 1 ? 'cms_updates.pill.themes_singular' : 'cms_updates.pill.themes_plural', ['count' => $data['theme_updates']]) }}</span> @endif
    </p>

    @if (! empty($data['plugin_updates_list']))
        <div class="table-scroll">
            <table class="data">
                <thead><tr><th>{{ ReportLang::get('cms_updates.plugins.col.plugin') }}</th><th>{{ ReportLang::get('cms_updates.plugins.col.installed') }}</th><th>{{ ReportLang::get('cms_updates.plugins.col.available') }}</th></tr></thead>
                <tbody>
                    @foreach ($data['plugin_updates_list'] as $plugin)
                        <tr>
                            <td>{{ $plugin['name'] ?? ReportLang::get('cms_updates.plugin_fallback') }}</td>
                            <td class="muted">{{ $plugin['current'] ?? '—' }}</td>
                            <td>{{ $plugin['available'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    <p class="muted" style="margin-top:8px; font-size:13px;">{{ ReportLang::get('cms_updates.care_note') }}</p>
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
