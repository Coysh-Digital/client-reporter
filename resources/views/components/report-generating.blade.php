@props(['report'])

{{--
    Shared "generating your report" overlay, used by both the report builder and
    the report page so the wait looks the same wherever generation is kicked off.
    Shown while the report's generation is queued or running; polls the host
    Livewire component's pollGeneration() (present on both), which moves on to the
    finished report once it lands. The cycling messages are cosmetic.
--}}
@if ($report->isGenerating())
    <div wire:poll.2s="pollGeneration" wire:key="generating-overlay"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background:color-mix(in srgb, var(--color-ink) 45%, transparent);backdrop-filter:blur(2px);">
        <div class="w-full max-w-sm rounded-2xl border border-line bg-surface p-6 text-center shadow-xl"
             x-data="{ i: 0, timer: null, messages: [
                'Collecting the latest data…',
                'Crunching the numbers…',
                'Rendering charts…',
                'Writing summaries…',
                'Putting it all together…',
             ] }"
             x-init="timer = setInterval(() => { i = (i + 1) % messages.length }, 1800)"
             x-on:destroy="clearInterval(timer)">
            <div class="mx-auto mb-4 flex h-11 w-11 items-center justify-center rounded-full" style="background:var(--color-accent-soft);">
                <x-icon name="file-chart-column" class="h-5 w-5" style="color:var(--color-accent);" />
            </div>
            <p class="text-md font-semibold text-ink">Generating your report</p>
            <p class="mt-1 h-4 text-sm text-muted" x-text="messages[i]"></p>
            <div class="cr-progress mt-4"><div class="cr-progress-bar"></div></div>
            <p class="mt-3 text-2xs text-faint">
                {{ $report->generation_status === \App\Enums\GenerationStatus::Queued
                    ? 'Waiting for the background worker to pick this up…'
                    : 'This can take a moment while we gather fresh data.' }}
            </p>
        </div>
    </div>
@endif
