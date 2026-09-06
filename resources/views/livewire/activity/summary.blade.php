<div wire:poll.5s class="grid grid-cols-2 gap-3 sm:grid-cols-4" aria-live="polite">
    @php
        $tiles = [
            ['label' => 'Queued', 'value' => $queued, 'tone' => 'text-ink'],
            ['label' => 'Running', 'value' => $running, 'tone' => $running > 0 ? 'text-info' : 'text-ink'],
            ['label' => 'Failed (24h)', 'value' => $failedRecently, 'tone' => $failedRecently > 0 ? 'text-danger' : 'text-ink'],
            ['label' => 'Failed jobs', 'value' => $failedJobsCount, 'tone' => $failedJobsCount > 0 ? 'text-danger' : 'text-ink'],
        ];
    @endphp
    @foreach ($tiles as $tile)
        <div class="cr-card px-4 py-3">
            <div class="cr-eyebrow">{{ $tile['label'] }}</div>
            <div class="tnum mt-1 font-serif text-2xl font-semibold {{ $tile['tone'] }}">{{ $tile['value'] }}</div>
        </div>
    @endforeach
</div>
