@if ($heading)
    @include('reports.blocks.partials.heading', ['text' => $heading, 'icon' => $icon ?? 'document'])
@endif
<p class="muted">This section is temporarily unavailable.</p>
