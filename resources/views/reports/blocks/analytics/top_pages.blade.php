@php
    use App\Support\Format;
    use App\Support\ReportLang;
    $pages = $data['pages'] ?? [];
    $maxViews = $pages ? max(array_map(fn ($p) => (float) ($p['pageviews'] ?? 0), $pages)) : 0;
    $maxViews = max(1.0, $maxViews);
@endphp
@include('reports.blocks.partials.heading', ['text' => $heading ?: ReportLang::get('top_pages.heading'), 'icon' => $icon ?? 'chart', 'suffix' => $data['provider'] ?? null])

@if (empty($pages))
    <p class="muted">{{ ReportLang::get('top_pages.empty') }}</p>
@else
    <div class="table-scroll">
        <table class="data">
            <thead><tr><th>{{ ReportLang::get('top_pages.col.page') }}</th><th style="width:34%;">{{ ReportLang::get('top_pages.col.views') }}</th><th>{{ ReportLang::get('top_pages.col.visitors') }}</th></tr></thead>
            <tbody>
                @foreach ($pages as $page)
                    @php $pct = round((float) ($page['pageviews'] ?? 0) / $maxViews * 100); @endphp
                    <tr>
                        <td>{{ $page['label'] ?: '/' }}</td>
                        <td>
                            <table class="bars"><tr>
                                <td style="padding:0;"><span class="bar-track"><span class="bar-fill" style="width:{{ $pct }}%;"></span></span></td>
                                <td style="padding:0 0 0 10px;width:52px;text-align:right;color:#57534a;">{{ Format::number($page['pageviews'] ?? 0) }}</td>
                            </tr></table>
                        </td>
                        <td>{{ Format::number($page['visitors'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($commentary) <div class="commentary">{!! nl2br(e($commentary)) !!}</div> @endif
