@php use App\Support\Format; use App\Support\ReportLang; @endphp

@include('reports.blocks.partials.heading', ['text' => $heading ?: ReportLang::get('leads.heading'), 'icon' => $icon ?? 'chart', 'suffix' => $data['provider'] ?? null])

@if (! ($data['has_data'] ?? false) || empty($data['metrics']))
    <p class="muted">{{ ReportLang::get('leads.empty') }}</p>
@else
    @include('reports.blocks.partials.insight', ['insight' => empty($data['ai_summary']) ? ($data['insight'] ?? null) : null])
    @include('reports.blocks.partials.ai-summary', ['aiSummary' => $data['ai_summary'] ?? null])
    @include('reports.blocks.partials.metric-grid', ['metrics' => $data['metrics']])

    @if (! empty($data['campaigns']))
        <div class="mini-bars-title" style="margin-top:20px;">{{ ReportLang::get('email.heading') }}</div>
        <div class="table-scroll">
            <table class="data" style="margin-top:8px;">
                <thead><tr>
                    <th>{{ ReportLang::get('email.col.campaign') }}</th>
                    <th>{{ ReportLang::get('email.col.sent') }}</th>
                    <th>{{ ReportLang::get('email.col.recipients') }}</th>
                    <th>{{ ReportLang::get('email.col.opens') }}</th>
                    <th>{{ ReportLang::get('email.col.clicks') }}</th>
                    <th>{{ ReportLang::get('email.col.unsubscribes') }}</th>
                </tr></thead>
                <tbody>
                    @foreach ($data['campaigns'] as $campaign)
                        <tr>
                            <td>{{ $campaign['name'] ?: '—' }}</td>
                            <td>{{ $campaign['sent_at'] }}</td>
                            <td>{{ Format::number($campaign['recipients'] ?? 0) }}</td>
                            <td>{{ Format::number($campaign['opens'] ?? 0) }} · {{ Format::percent($campaign['open_rate'] ?? 0, 1) }}</td>
                            <td>{{ Format::number($campaign['clicks'] ?? 0) }} · {{ Format::percent($campaign['click_rate'] ?? 0, 1) }}</td>
                            <td>{{ $campaign['unsubscribed'] === null ? '—' : Format::number($campaign['unsubscribed']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
