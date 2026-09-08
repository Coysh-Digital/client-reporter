<div>
    <x-breadcrumbs :items="[['label' => 'Reports', 'href' => route('reports.index')], ['label' => $report->site->name, 'href' => route('sites.show', $report->site)], ['label' => $report->title]]" />

    @if ($report->generationFailed())
        <x-alert variant="danger" class="mb-4" title="Generation failed">
            {{ $report->generation_error }}
            @can('manage-reports')
                <x-slot:action><x-button size="sm" wire:click="retryGeneration" icon="arrow-path">Try again</x-button></x-slot:action>
            @endcan
        </x-alert>
    @elseif (! $report->isGenerated() && ! $report->isGenerating())
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
                <x-button wire:click="duplicate" icon="document-duplicate">
                    <span wire:loading.remove wire:target="duplicate">Duplicate</span>
                    <span wire:loading wire:target="duplicate">Duplicating…</span>
                </x-button>
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

            {{-- Schedule: edits the parent site's schedule, so it applies to
                 every future report for this site. --}}
            <div class="cr-card px-5 py-4">
                <h3 class="cr-eyebrow">Schedule</h3>
                @can('manage-sites')
                    <form wire:submit="saveSchedule" class="mt-3 space-y-3">
                        <x-field label="Frequency" for="sched-frequency">
                            <select wire:model.live="report_frequency" id="sched-frequency" class="cr-input">
                                @foreach ($this->frequencies() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-field>

                        @if ($report_frequency !== 'none')
                            <x-field label="Report template" for="sched-template" optional>
                                <select wire:model="report_template_id" id="sched-template" class="cr-input">
                                    <option value="">Default sections</option>
                                    @foreach ($this->templates() as $template)
                                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                                    @endforeach
                                </select>
                            </x-field>

                            <x-field label="Auto-send to client" for="sched-auto-send" help="Email each new report to {{ $report->site->client->contact_email ?: 'the client contact' }} automatically once it generates.">
                                <select wire:model="auto_send" id="sched-auto-send" class="cr-input">
                                    <option value="">Use workspace default</option>
                                    <option value="yes">Always send</option>
                                    <option value="no">Never send</option>
                                </select>
                            </x-field>
                        @endif

                        <x-button type="submit" size="sm" variant="primary">
                            <span wire:loading.remove wire:target="saveSchedule">Save schedule</span>
                            <span wire:loading wire:target="saveSchedule">Saving…</span>
                        </x-button>
                    </form>
                @else
                    <dl class="mt-3 space-y-2.5 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-muted">Frequency</dt><dd class="text-ink">{{ $report->site->report_frequency->label() }}</dd></div>
                        @if ($report->site->hasReportSchedule())
                            <div class="flex justify-between gap-3"><dt class="text-muted">Auto-send</dt><dd class="text-ink">{{ $report->site->autoSends() ? 'On' : 'Off' }}{{ $report->site->autoSendSetting() === null ? ' (default)' : '' }}</dd></div>
                        @endif
                    </dl>
                @endcan
            </div>

            {{-- Delivery history: what was emailed, when, and whether it landed. --}}
            @if ($deliveries->isNotEmpty())
                <div class="cr-card px-5 py-4">
                    <h3 class="cr-eyebrow">Delivery history</h3>
                    <ul class="mt-3 space-y-3 text-sm">
                        @foreach ($deliveries as $delivery)
                            <li wire:key="delivery-{{ $delivery->id }}" class="flex items-start justify-between gap-3">
                                <span class="min-w-0">
                                    <span class="block truncate text-ink">{{ $delivery->recipient ?: 'Not sent' }}</span>
                                    <span class="block text-xs text-faint">{{ $delivery->created_at->format('j M Y, H:i') }} · {{ $delivery->trigger->label() }}</span>
                                    @if (! $delivery->succeeded && $delivery->error)
                                        <span class="mt-0.5 block text-xs" style="color:var(--color-danger);">{{ $delivery->error }}</span>
                                    @endif
                                </span>
                                <x-badge :variant="$delivery->succeeded ? 'ok' : 'danger'">{{ $delivery->succeeded ? 'Sent' : 'Failed' }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <x-report-generating :report="$report" />
</div>
