@include('reports.blocks.partials.heading', ['text' => $heading ?: \App\Support\ReportLang::get('website_overview.heading'), 'icon' => $icon ?? 'globe'])

<div class="table-scroll">
    <table class="data">
        <tbody>
            <tr>
                <th style="width: 30%;">{{ \App\Support\ReportLang::get('website_overview.row.website') }}</th>
                <td>{{ $data['host'] ?? '' }}</td>
            </tr>
            @if (! empty($data['cms']))
                <tr>
                    <th>{{ \App\Support\ReportLang::get('website_overview.row.platform') }}</th>
                    <td>{{ $data['cms'] }}</td>
                </tr>
            @endif
            <tr>
                <th>{{ \App\Support\ReportLang::get('website_overview.row.environment') }}</th>
                <td>{{ $data['environment'] ?? '' }}</td>
            </tr>
        </tbody>
    </table>
</div>

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
