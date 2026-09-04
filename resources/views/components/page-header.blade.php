@props(['title', 'subtitle' => null, 'eyebrow' => null])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-end justify-between gap-4']) }}>
    <div>
        @if ($eyebrow)
            <div class="cr-eyebrow">{{ $eyebrow }}</div>
        @endif
        <h1 @class(['font-serif text-2xl font-semibold tracking-tight text-ink', 'mt-1.5' => $eyebrow])>{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
