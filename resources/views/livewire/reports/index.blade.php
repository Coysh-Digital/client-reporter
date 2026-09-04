<div>
    <x-page-header title="Reports" subtitle="Every report you've built for your clients." eyebrow="Portfolio">
        <x-slot:actions>
            @can('manage-reports')
                <a href="{{ route('reports.create') }}" wire:navigate class="cr-btn cr-btn-primary">
                    <x-icon name="plus" class="h-3.5 w-3.5" />
                    New report
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex gap-1 rounded-lg border border-line bg-surface p-0.5 w-fit">
        @foreach (['all' => 'All', 'draft' => 'Draft', 'final' => 'Generated'] as $key => $label)
            <button type="button" wire:click="setStatus('{{ $key }}')"
                    @class([
                        'rounded-md px-3 py-1.5 text-[13px] font-medium transition',
                        'bg-accent-soft text-accent' => $status === $key,
                        'text-muted hover:text-ink' => $status !== $key,
                    ])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($reports->isEmpty())
        <x-empty-state icon="file-chart-column" title="No reports yet" description="Create your first report to turn connected data into a branded client report." />
    @else
        <div class="cr-panel">
            <div class="hidden items-center gap-4 border-b border-line px-5 py-2.5 text-[10.5px] font-bold uppercase tracking-[0.07em] text-faint sm:grid sm:grid-cols-[minmax(0,1fr)_170px_140px_150px]">
                <span>Report</span><span>Period</span><span>Status</span><span class="text-right">&nbsp;</span>
            </div>
            @foreach ($reports as $report)
                <div wire:key="report-{{ $report->id }}"
                     class="flex flex-col gap-3 px-5 py-3.5 hover:bg-paper sm:grid sm:grid-cols-[minmax(0,1fr)_170px_140px_150px] sm:items-center sm:gap-4"
                     @if (! $loop->last) style="border-bottom:1px solid var(--color-line);" @endif>
                    <a href="{{ route('reports.show', $report) }}" wire:navigate class="flex min-w-0 items-center gap-3">
                        <x-avatar :name="$report->site->client->name" size="lg" />
                        <span class="min-w-0">
                            <span class="block truncate text-[14px] font-semibold text-ink">{{ $report->title }}</span>
                            <span class="block truncate text-[12.5px] text-faint">{{ $report->site->client->name }} · {{ $report->site->host() }}</span>
                        </span>
                    </a>
                    <div class="flex flex-wrap items-center justify-between gap-3 sm:contents">
                        <span class="tnum text-[13px] text-muted">{{ $report->dateRange()->label() }}</span>
                        <span>
                            @if ($report->status === 'final')
                                <x-status-dot variant="ok" label="Generated" />
                            @else
                                <x-status-dot variant="neutral" label="Draft" />
                            @endif
                        </span>
                        <span class="flex items-center gap-3 text-[12.5px] sm:justify-end">
                            <a href="{{ route('reports.show', $report) }}" wire:navigate class="font-semibold" style="color:var(--color-accent);">{{ $report->status === 'final' ? 'View' : 'Open' }}</a>
                            @can('manage-reports')
                                <button wire:click="delete({{ $report->id }})" wire:confirm="Delete this report?" class="text-faint hover:text-danger" title="Delete">
                                    <x-icon name="trash-can" class="h-3.5 w-3.5" />
                                </button>
                            @endcan
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $reports->links() }}</div>
    @endif
</div>
