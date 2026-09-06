@if ($heading)
    @include('reports.blocks.partials.heading', ['text' => $heading, 'icon' => $icon ?? 'document', 'variant' => 'title'])
@endif

@if ($commentary)
    <div style="color: #33302b; font-size: 15px;">{!! nl2br(e($commentary)) !!}</div>
@elseif (! ($frozen ?? false))
    {{-- A builder-only hint; a generated report never prints it. --}}
    <p class="muted">{{ \App\Support\ReportLang::get('text.empty') }}</p>
@endif
