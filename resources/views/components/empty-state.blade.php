@props(['title', 'description' => null, 'icon' => null])

<div {{ $attributes->merge(['class' => 'cr-card px-6 py-12 text-center']) }}>
    @if ($icon)
        <div class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-paper text-faint">
            <x-icon :name="$icon" class="h-4 w-4" />
        </div>
    @endif
    <p class="font-medium text-ink">{{ $title }}</p>
    @if ($description)
        <p class="mx-auto mt-1 max-w-md text-sm text-muted">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-4 flex justify-center">{{ $action }}</div>
    @endisset
</div>
