<div>
    @if (session('status'))
        <div class="mb-4 rounded-md bg-ok-soft px-3 py-2 text-sm text-ok">{{ session('status') }}</div>
    @endif

    <div class="mb-2 text-sm text-muted">
        <a href="{{ route('reports.index') }}" wire:navigate class="hover:text-ink">Reports</a>
        <span class="text-faint">/</span>
        <a href="{{ route('sites.show', $report->site) }}" wire:navigate class="hover:text-ink">{{ $report->site->name }}</a>
    </div>

    <x-page-header :title="$report->title" :subtitle="$report->site->client->name . ' · ' . $report->dateRange()->label()">
        <x-slot:actions>
            @can('manage-reports')
                <a href="{{ route('reports.edit', $report) }}" wire:navigate class="cr-btn cr-btn-secondary">Edit</a>
                <button wire:click="generate" class="cr-btn cr-btn-secondary">
                    <span wire:loading.remove wire:target="generate">{{ $report->isGenerated() ? 'Regenerate' : 'Generate' }}</span>
                    <span wire:loading wire:target="generate">Working…</span>
                </button>
                @if ($report->isGenerated())
                    <livewire:reports.share-panel :report="$report" :key="'share-'.$report->id" />
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-4">
        <div class="lg:col-span-3">
            @unless ($report->isGenerated())
                <div class="mb-4 rounded-md bg-warn-soft px-4 py-3 text-sm text-warn">
                    This report is a draft. Generate it to freeze the data for sharing, PDF and email.
                </div>
            @endunless

            <div class="overflow-hidden rounded-lg border border-line bg-white" style="height: 780px;">
                <iframe src="{{ route('reports.preview', $report) }}{{ $report->isGenerated() ? '?frozen=1' : '' }}"
                        class="h-full w-full" style="border: 0;"></iframe>
            </div>
        </div>

        <div class="space-y-4">
            <div class="cr-card px-5 py-4">
                <h3 class="text-xs font-medium uppercase tracking-wide text-faint">Details</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    <div><dt class="text-muted">Status</dt><dd>@if ($report->isGenerated())<x-badge variant="ok">Generated</x-badge>@else<x-badge variant="neutral">Draft</x-badge>@endif</dd></div>
                    <div><dt class="text-muted">Period</dt><dd class="text-ink">{{ $report->dateRange()->label() }}</dd></div>
                    <div><dt class="text-muted">Comparison</dt><dd class="text-ink">{{ $report->compare_previous ? 'Previous period' : 'Off' }}</dd></div>
                    @if ($report->generated_at)
                        <div><dt class="text-muted">Generated</dt><dd class="text-ink">{{ $report->generated_at->diffForHumans() }}</dd></div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>
