{{ $report->title }}
{{ $report->site->name }} · {{ $report->dateRange()->label() }}

@if ($customMessage)
{{ $customMessage }}
@else
Your latest website report is ready.
@endif

View your report: {{ $url }}

--
{{ $branding->emailFooter ?: $branding->agencyName }}
