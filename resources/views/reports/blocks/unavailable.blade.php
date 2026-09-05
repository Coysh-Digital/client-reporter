@if ($heading)
    @include('reports.blocks.partials.heading', ['text' => $heading, 'icon' => $icon ?? 'document'])
@endif
<p class="muted">{{ \App\Support\ReportLang::get('unavailable.body') }}</p>
