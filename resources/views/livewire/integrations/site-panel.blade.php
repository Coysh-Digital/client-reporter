<div>
    @if (session('panel_status'))
        <div class="mb-4 rounded-md bg-ok-soft px-3 py-2 text-sm text-ok">{{ session('panel_status') }}</div>
    @endif

    {{-- Connected services --}}
    @if ($connections->isNotEmpty())
        <div class="mb-6 cr-card divide-y divide-line">
            @foreach ($connections as $connection)
                @php $manifest = $connection->integration()?->manifest(); @endphp
                <div wire:key="conn-{{ $connection->id }}" class="px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-ink">{{ $connection->name }}</span>
                                <x-badge :variant="$connection->status->badge()">{{ $connection->status->label() }}</x-badge>
                                @if ($connection->usesWorkspace())
                                    <span class="rounded-full bg-accent-soft px-2 py-0.5 text-[11px] font-medium" style="color:var(--color-accent)">Workspace</span>
                                @endif
                            </div>
                            <div class="mt-0.5 text-xs text-muted">
                                {{ $manifest?->name ?? $connection->integration_key }}
                                @if ($connection->last_collected_at)
                                    · Last collected {{ $connection->last_collected_at->diffForHumans() }}
                                @endif
                            </div>
                            @if ($connection->last_error)
                                <p class="mt-2 rounded bg-danger-soft px-2 py-1 text-xs text-danger">{{ $connection->last_error }}</p>
                            @endif

                            @php $insight = $insights[$connection->id] ?? null; @endphp
                            @if ($insight)
                                <div class="mt-3">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($insight['chips'] as $chip)
                                            <div class="rounded-md bg-paper px-2.5 py-1.5">
                                                <div class="text-[11px] text-faint">{{ $chip['label'] }}</div>
                                                <div class="tnum text-sm font-semibold text-ink">{{ $chip['value'] }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="mt-3 max-w-md" wire:ignore>
                                        <p class="mb-1 text-[11px] text-faint">{{ $insight['chart']['label'] }} · by period</p>
                                        <div class="h-40" x-data="crBarChart(@js($insight['chart']))">
                                            <canvas x-ref="canvas"></canvas>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        @can('manage-integrations')
                            <div class="flex shrink-0 items-center gap-3 text-sm">
                                <button wire:click="collectNow({{ $connection->id }})" wire:loading.attr="disabled" class="text-muted hover:text-ink">
                                    <span wire:loading.remove wire:target="collectNow({{ $connection->id }})">Collect now</span>
                                    <span wire:loading wire:target="collectNow({{ $connection->id }})">Collecting…</span>
                                </button>
                                <a href="{{ route('integrations.edit', $connection) }}" wire:navigate class="text-muted hover:text-ink">Manage</a>
                                <button wire:click="disconnect({{ $connection->id }})"
                                        wire:confirm="Disconnect {{ $connection->name }}? Collected data will be removed."
                                        class="text-danger hover:underline">Disconnect</button>
                            </div>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <x-empty-state class="mb-6" title="No integrations connected"
                       description="Connect analytics, uptime and CMS services to start collecting data for this site." />
    @endif

    {{-- Available services --}}
    @can('manage-integrations')
        <div class="cr-card px-5 py-4">
            <h3 class="text-xs font-medium uppercase tracking-wide text-faint">Connect a service</h3>
            @if ($available === [])
                <p class="mt-3 text-sm text-muted">Every available service is already connected — here or once for the whole workspace.</p>
            @endif
            <div class="mt-3 space-y-4">
                @foreach ($available as $category => $integrations)
                    <div>
                        <p class="mb-1.5 text-xs font-medium text-muted">{{ \App\Integrations\Support\IntegrationCategory::from($category)->label() }}</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($integrations as $integration)
                                @php $m = $integration->manifest(); @endphp
                                <a href="{{ route('sites.integrations.connect', ['site' => $site, 'key' => $m->key]) }}" wire:navigate
                                   wire:key="avail-{{ $m->key }}"
                                   class="flex items-center justify-between rounded-md border border-line px-3 py-2 text-sm hover:border-line-strong hover:bg-paper">
                                    <span class="font-medium text-ink">{{ $m->name }}</span>
                                    <span class="text-xs text-accent">Connect →</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endcan
</div>
