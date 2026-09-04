@include('reports.blocks.partials.heading', ['text' => $heading ?: 'Thank you', 'icon' => $icon ?? 'document', 'variant' => 'title'])

@if ($commentary)
    <div style="color: #33302b;">{!! nl2br(e($commentary)) !!}</div>
@endif

@if (($data['email'] ?? null) || ($data['phone'] ?? null) || ($data['website'] ?? null))
    <p class="muted" style="margin-top: 16px;">
        Get in touch:
        @if ($data['email'] ?? null) <a href="mailto:{{ $data['email'] }}">{{ $data['email'] }}</a> @endif
        @if ($data['phone'] ?? null) &middot; {{ $data['phone'] }} @endif
        @if ($data['website'] ?? null) &middot; <a href="{{ $data['website'] }}">{{ $data['website'] }}</a> @endif
    </p>
@endif
