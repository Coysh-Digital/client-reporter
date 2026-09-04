@if ($heading)
    @include('reports.blocks.partials.heading', ['text' => $heading, 'icon' => $icon ?? 'document', 'variant' => 'title'])
@endif

@if ($commentary)
    <div style="color: #33302b; font-size: 15px;">{!! nl2br(e($commentary)) !!}</div>
@else
    <p class="muted">Add your commentary for this section.</p>
@endif
