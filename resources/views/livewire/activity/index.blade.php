<div wire:poll.5s>
    <x-page-header title="Activity"
                   subtitle="Background data collection — what's queued, running and recently finished. Updates live." />

    {{-- Summary tiles --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @php
            $tiles = [
                ['label' => 'Queued', 'value' => $queued, 'tone' => 'text-ink'],
                ['label' => 'Running', 'value' => $running, 'tone' => $running > 0 ? 'text-info' : 'text-ink'],
                ['label' => 'Failed (24h)', 'value' => $failedRecently, 'tone' => $failedRecently > 0 ? 'text-danger' : 'text-ink'],
                ['label' => 'Failed jobs', 'value' => $failedJobs, 'tone' => $failedJobs > 0 ? 'text-danger' : 'text-ink'],
            ];
        @endphp
        @foreach ($tiles as $tile)
            <div class="cr-card px-4 py-3">
                <div class="text-xs font-medium uppercase tracking-wide text-faint">{{ $tile['label'] }}</div>
                <div class="tnum mt-1 text-2xl font-semibold {{ $tile['tone'] }}">{{ $tile['value'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Filter --}}
    <div class="mt-5 flex items-center gap-1.5 text-sm">
        @foreach (['all' => 'All', 'running' => 'Running', 'success' => 'Success', 'failed' => 'Failed'] as $key => $label)
            <button wire:click="setFilter('{{ $key }}')" @class([
                'rounded-md px-2.5 py-1 font-medium transition',
                'bg-surface text-ink ring-1 ring-line shadow-sm' => $filter === $key,
                'text-muted hover:text-ink' => $filter !== $key,
            ])>{{ $label }}</button>
        @endforeach
    </div>

    {{-- Runs --}}
    <div class="mt-3">
        @if ($runs->isEmpty())
            <x-empty-state title="No collection activity yet"
                           description="Runs appear here when data is collected — automatically on schedule, or when you use Collect now on a site." />
        @else
            <div class="cr-card divide-y divide-line">
                @foreach ($runs as $run)
                    @php
                        $site = $run->siteIntegration?->site;
                        $integrationName = $run->siteIntegration?->integration()?->manifest()->name
                            ?? ucfirst((string) $run->siteIntegration?->integration_key);
                        $duration = $run->duration_ms === null
                            ? null
                            : ($run->duration_ms >= 1000 ? round($run->duration_ms / 1000, 1).'s' : $run->duration_ms.'ms');
                        [$variant, $statusLabel] = match ($run->status) {
                            'success' => ['ok', 'Success'],
                            'failed' => ['danger', 'Failed'],
                            'running' => ['info', 'Running'],
                            default => ['neutral', ucfirst($run->status)],
                        };
                    @endphp
                    <div wire:key="run-{{ $run->id }}" class="flex items-start justify-between gap-4 px-5 py-3.5">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <x-badge :variant="$variant">{{ $statusLabel }}</x-badge>
                                <span class="truncate font-medium text-ink">{{ $site?->name ?? 'Unknown site' }}</span>
                                <span class="text-faint">·</span>
                                <span class="truncate text-muted">{{ $integrationName }}</span>
                            </div>
                            <div class="mt-0.5 text-xs text-faint">
                                {{ $run->collector_key }}
                                @if ($run->started_at) · {{ $run->started_at->diffForHumans() }} @endif
                                @if ($duration) · {{ $duration }} @endif
                                @if ($run->status === 'success') · {{ number_format($run->records_written) }} {{ Str::plural('record', $run->records_written) }} @endif
                            </div>
                            @if ($run->status === 'failed' && $run->error_message)
                                <div class="mt-1.5 rounded bg-danger-soft px-2 py-1 text-xs text-danger">{{ $run->error_message }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
