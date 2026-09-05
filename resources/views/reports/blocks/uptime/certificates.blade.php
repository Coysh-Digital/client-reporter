@php
    use App\Support\ReportLang;
    $ratingStyle = [
        'good' => 'background:#eaf3ec;color:#3f7d54;',
        'needs-improvement' => 'background:#f7efe1;color:#a4712a;',
        'poor' => 'background:#f6e9e7;color:#a13b32;',
    ];
@endphp
@include('reports.blocks.partials.heading', ['text' => $heading ?: ReportLang::get('certificates.heading'), 'icon' => $icon ?? 'globe'])

@if (! ($data['has_data'] ?? false))
    <p class="muted">{{ ReportLang::get('certificates.empty') }}</p>
@else
    <div class="table-scroll">
        <table class="data" style="margin-top:4px;">
            <thead><tr><th>{{ ReportLang::get('certificates.col.endpoint') }}</th><th>{{ ReportLang::get('certificates.col.expires_in') }}</th><th>{{ ReportLang::get('certificates.col.status') }}</th></tr></thead>
            <tbody>
                @foreach ($data['certificates'] as $cert)
                    @php
                        $days = $cert['days'];
                        $label = $days < 0 ? ReportLang::get('certificates.expiry.expired') : ($days === 1 ? ReportLang::get('certificates.expiry.one_day') : ReportLang::get('certificates.expiry.days', ['days' => $days]));
                        $status = $cert['rating'] === 'good' ? ReportLang::get('certificates.status.valid') : ($cert['rating'] === 'needs-improvement' ? ReportLang::get('certificates.status.renew_soon') : ($days < 0 ? ReportLang::get('certificates.status.expired') : ReportLang::get('certificates.status.renew_now')));
                    @endphp
                    <tr>
                        <td>{{ $cert['host'] ?: '—' }}</td>
                        <td>{{ $label }}</td>
                        <td><span class="pill" style="{{ $ratingStyle[$cert['rating']] }}">{{ $status }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($commentary)
    <div class="commentary">{!! nl2br(e($commentary)) !!}</div>
@endif
