<div {{ $report->isGenerating() ? 'wire:poll.3s=pollGeneration' : '' }}>
    <x-breadcrumbs :items="[['label' => 'Reports', 'href' => route('reports.index')], ['label' => $report->site->name, 'href' => route('sites.show', $report->site)], ['label' => $report->title]]" />

    @if ($report->generationFailed())
        <x-alert variant="danger" class="mb-4" title="Generation failed">
            {{ $report->generation_error }}
            @can('manage-reports')
                <x-slot:action><x-button size="sm" wire:click="retryGeneration" icon="arrow-path">Try again</x-button></x-slot:action>
            @endcan
        </x-alert>
    @elseif ($report->isGenerating())
        <x-alert variant="info" class="mb-4">
            <span class="inline-flex items-center gap-2">
                <span class="inline-block h-2 w-2 animate-pulse rounded-full" style="background:var(--color-info);" aria-hidden="true"></span>
                {{ $report->generation_status === \App\Enums\GenerationStatus::Queued ? 'Generation is queued and will start shortly.' : 'Generating this report in the background — fresh data is being collected.' }}
            </span>
        </x-alert>
    @elseif (! $report->isGenerated())
        <x-alert variant="warn" class="mb-4">
            This report is a draft. Generate it to freeze the data for sharing, PDF and email.
        </x-alert>
    @endif

    <x-page-header :title="$report->title" :subtitle="$report->site->client->name . ' · ' . $report->dateRange()->label()">
        <x-slot:actions>
            @if ($report->isGenerated())
                <x-button :href="route('reports.pdf', $report)" :navigate="false" icon="file-chart-column">Download PDF</x-button>
            @endif
            <x-button :href="route('reports.preview', $report) . ($report->isGenerated() ? '?frozen=1' : '')" :navigate="false" target="_blank" rel="noopener" icon="arrow-up-right-from-square">Open in new tab</x-button>
            @can('manage-reports')
                <x-button :href="route('reports.edit', $report)" icon="pencil-square">Edit sections</x-button>
                @if ($report->isGenerated())
                    <x-button wire:click="generate" :disabled="$report->isGenerating()">
                        <span wire:loading.remove wire:target="generate">{{ $report->isGenerating() ? 'Generating…' : 'Regenerate' }}</span>
                        <span wire:loading wire:target="generate">Queuing…</span>
                    </x-button>
                    <livewire:reports.share-panel :report="$report" :key="'share-'.$report->id" />
                @else
                    <x-button variant="primary" wire:click="generate" :disabled="$report->isGenerating()" icon="file-chart-column">
                        <span wire:loading.remove wire:target="generate">{{ $report->isGenerating() ? 'Generating…' : 'Generate' }}</span>
                        <span wire:loading wire:target="generate">Queuing…</span>
                    </x-button>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-4">
        <div class="lg:col-span-3">
            <div class="overflow-hidden rounded-xl border border-line bg-white" style="height: calc(100vh - 240px); min-height: 560px;">
                <iframe src="{{ route('reports.preview', $report) }}{{ $report->isGenerated() ? '?frozen=1' : '' }}"
                        title="Preview of {{ $report->title }}" class="h-full w-full" style="border: 0;"></iframe>
            </div>
        </div>

        <div class="space-y-4">
            <div class="cr-card px-5 py-4">
                <h3 class="cr-eyebrow">Details</h3>
                <dl class="mt-3 space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-muted">Status</dt><dd>@if ($report->isGenerating())<x-badge variant="info">{{ $report->generation_status->label() }}</x-badge>@elseif ($report->isGenerated())<x-badge :variant="$report->periodStatus()->badge()">{{ $report->periodStatus()->label() }}</x-badge>@else<x-badge variant="neutral">Draft</x-badge>@endif</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Period</dt><dd class="text-right text-ink">{{ $report->dateRange()->label() }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Comparison</dt><dd class="text-ink">{{ $report->compare_previous ? 'Previous period' : 'Off' }}</dd></div>
                    @if ($report->generated_at)
                        <div class="flex justify-between gap-3"><dt class="text-muted">Generated</dt><dd class="text-ink">{{ $report->generated_at->diffForHumans() }}</dd></div>
                    @endif
                    <div class="flex justify-between gap-3"><dt class="text-muted">Site</dt><dd class="min-w-0 truncate text-right"><a href="{{ route('sites.show', $report->site) }}" wire:navigate class="cr-link">{{ $report->site->name }}</a></dd></div>
                </dl>
            </div>
        </div>
    </div>
</div>
