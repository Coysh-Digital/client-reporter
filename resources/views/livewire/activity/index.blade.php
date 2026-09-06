<div>
    <x-page-header title="Activity" eyebrow="Setup"
                   subtitle="Background work — what's on the queue, recent collection runs, and failed jobs." />

    <livewire:activity.summary />

    {{-- Tabs --}}
    <div class="mt-5 flex flex-wrap items-center gap-3">
        <x-segmented :options="[
                'runs' => 'Collection runs',
                'queued' => 'Queued'.($queued > 0 ? ' ('.$queued.')' : ''),
                'failed' => 'Failed jobs'.($failedJobsCount > 0 ? ' ('.$failedJobsCount.')' : ''),
            ]" :value="$tab" action="setTab" label="Activity view" />
        @if ($tab === 'runs')
            <x-segmented :options="['all' => 'All', 'success' => 'Succeeded', 'failed' => 'Failed', 'running' => 'Running']" :value="$status" action="setStatus" label="Filter runs by outcome" />
            <label class="sr-only" for="activity-site">Site</label>
            <select wire:model.live="site" id="activity-site" class="cr-input w-auto py-1.5 pr-8 text-sm">
                <option value="">All sites</option>
                @foreach ($sites as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
        @endif
    </div>

    {{-- Queued --}}
    @if ($tab === 'queued')
        <div class="mt-4">
            @if (empty($queuedJobs))
                <x-empty-state title="Nothing on the queue"
                               description="Queued background jobs appear here while they wait to run." />
            @else
                <div class="mb-2 flex justify-end">
                    <x-confirm-button action="clearQueued" title="Clear the pending queue?" message="Jobs still waiting are removed; anything a worker is already running is left alone. Scheduled work re-queues on its next cycle." confirm="Clear queued" :danger="true" class="cr-btn cr-btn-danger cr-btn-sm">Clear queued</x-confirm-button>
                </div>
                <div class="cr-card divide-y divide-line">
                    @foreach ($queuedJobs as $job)
                        <div wire:key="job-{{ $job['id'] }}" class="flex items-center justify-between gap-4 px-5 py-3">
                            <div class="flex min-w-0 items-center gap-2">
                                <x-badge :variant="$job['reserved'] ? 'info' : 'neutral'">{{ $job['reserved'] ? 'Running' : 'Queued' }}</x-badge>
                                <span class="truncate font-medium text-ink">{{ $job['name'] }}</span>
                                @if ($job['attempts'] > 1) <span class="text-xs text-faint">attempt {{ $job['attempts'] }}</span> @endif
                            </div>
                            <span class="shrink-0 text-xs text-faint">waiting {{ $job['queued_at']->diffForHumans(null, true) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Failed jobs --}}
    @if ($tab === 'failed')
        <div class="mt-4">
            @if (empty($failedJobs))
                <x-empty-state title="No failed jobs" description="Jobs that fail after their retries appear here. 🎉" />
            @else
                <div class="mb-2 flex justify-end">
                    <x-confirm-button action="clearFailedJobs" title="Dismiss all failed jobs?" message="Their error details are removed from this list." confirm="Dismiss all" :danger="true" class="cr-btn cr-btn-danger cr-btn-sm">Clear all failed</x-confirm-button>
                </div>
                <div class="cr-card divide-y divide-line">
                    @foreach ($failedJobs as $job)
                        <div wire:key="failed-{{ $job['uuid'] }}" class="flex items-start justify-between gap-4 px-5 py-3.5">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <x-badge variant="danger">Failed</x-badge>
                                    <span class="truncate font-medium text-ink">{{ $job['name'] }}</span>
                                </div>
                                <div class="mt-0.5 text-xs text-faint">{{ $job['queue'] }} · {{ $job['failed_at']->diffForHumans() }}</div>
                                @if ($job['exception'] !== '')
                                    <div class="mt-1.5 rounded bg-danger-soft px-2 py-1 text-xs text-danger">{{ Str::limit($job['exception'], 200) }}</div>
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <x-button size="sm" wire:click="retryFailedJob('{{ $job['uuid'] }}')" icon="arrow-path">Retry</x-button>
                                <x-button size="sm" variant="ghost" wire:click="dismissFailedJob('{{ $job['uuid'] }}')">Dismiss</x-button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Collection runs --}}
    @if ($tab === 'runs' && $runs !== null)
        <div class="mt-4">
            @if ($runs->isEmpty())
                @if ($status !== 'all' || $site !== null)
                    <x-empty-state icon="magnifying-glass" title="No runs match" description="Try a different outcome or site.">
                        <x-slot:action><x-button wire:click="$set('status', 'all'); $set('site', null)">Clear filters</x-button></x-slot:action>
                    </x-empty-state>
                @else
                    <x-empty-state icon="bolt" title="No collection activity yet"
                                   description="Runs appear here when data is collected — automatically on schedule, or when you use Collect now on a site." />
                @endif
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
                                    @if (mb_strlen($run->error_message) > 140)
                                        <details class="mt-1.5 rounded bg-danger-soft px-2 py-1 text-xs text-danger">
                                            <summary class="cursor-pointer select-none font-medium">{{ Str::limit($run->error_message, 140) }}</summary>
                                            <p class="mt-1 whitespace-pre-line break-words">{{ $run->error_message }}</p>
                                        </details>
                                    @else
                                        <div class="mt-1.5 rounded bg-danger-soft px-2 py-1 text-xs text-danger">{{ $run->error_message }}</div>
                                    @endif
                                @endif
                            </div>
                            @if ($site)
                                <a href="{{ route('sites.show', $site) }}" wire:navigate class="cr-link shrink-0 text-xs">Open site</a>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">{{ $runs->links('vendor.pagination.cr') }}</div>
            @endif
        </div>
    @endif
</div>
