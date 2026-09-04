<div>
    <x-page-header title="Integrations" subtitle="The services Client Reporter can pull data from. Connect them per site." eyebrow="Setup">
        @can('manage-integrations')
            <x-slot:actions>
                <a href="{{ config('client-reporter.docs.integrations') }}" target="_blank" rel="noopener"
                   class="cr-btn cr-btn-secondary">
                    <x-icon name="arrow-up-right-from-square" class="h-3.5 w-3.5" />
                    Add integration
                </a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div class="space-y-8">
        @foreach ($grouped as $category => $integrations)
            @php $catCount = collect($integrations)->sum(fn ($i) => $counts[$i->manifest()->key] ?? 0); @endphp
            <section>
                <div class="mb-3 flex items-center gap-2">
                    <h2 class="cr-eyebrow">{{ \App\Integrations\Support\IntegrationCategory::from($category)->label() }}</h2>
                    @if ($catCount > 0)
                        <span class="tnum text-xs text-faint">{{ $catCount }} connected</span>
                    @endif
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($integrations as $integration)
                        @php
                            $m = $integration->manifest();
                            // A "provided by" integration (e.g. Craft Commerce) has no
                            // connection of its own — it rides on another integration, so
                            // its card links to that one and shows a "via X" note instead.
                            $providerName = null;
                            $connectKey = $m->key;
                            if ($m->providedBy) {
                                $provider = collect($grouped)->flatten()->first(fn ($i) => $i->manifest()->key === $m->providedBy);
                                $providerName = $provider?->manifest()->name ?? $m->providedBy;
                                $connectKey = $m->providedBy;
                            }
                            $canManage = auth()->user()->can('manage-integrations') && $siteCount > 0;
                            // Workspace-wide connection (one key for every site or client).
                            $supportsWorkspace = $integration->supportsWorkspaceScope();
                            $onlyWorkspace = $integration->onlyWorkspaceScope();
                            $ws = $workspace[$m->key] ?? null;
                            $mappedCount = $ws ? ($billingMappedCounts[$ws->id] ?? 0) : 0;
                            $connected = $onlyWorkspace ? ($ws?->status?->value === 'connected' ? 1 : 0) : ($m->providedBy ? 0 : ($counts[$m->key] ?? 0));
                            // Direct connect target: the one site's connect screen, else the picker.
                            // Skipped entirely for workspace-only integrations (billing) — there is
                            // no meaningful per-site connection to offer.
                            $connectUrl = ($canManage && ! $onlyWorkspace)
                                ? ($singleSite
                                    ? route('sites.integrations.connect', ['site' => $singleSite, 'key' => $connectKey])
                                    : route('sites.index'))
                                : null;
                            $workspaceUrl = $ws ? route('integrations.workspace.edit', $ws) : route('integrations.workspace.connect', $m->key);
                        @endphp
                        <div wire:key="cat-{{ $m->key }}" class="flex flex-col gap-1.5">
                            <a @if ($connectUrl) href="{{ $connectUrl }}" @elseif ($onlyWorkspace && $canManage) href="{{ $workspaceUrl }}" @endif
                               wire:navigate
                               @class([
                                   'cr-panel flex flex-1 flex-col px-5 py-4',
                                   'transition hover:border-line-strong hover:shadow-sm' => $connectUrl || ($onlyWorkspace && $canManage),
                               ])>
                                <div class="flex items-start gap-3">
                                    <x-avatar :name="$m->name" size="lg" :icon="$m->icon ? asset($m->icon) : null" />
                                    <div class="min-w-0 flex-1">
                                        <div class="text-[15px] font-semibold text-ink">{{ $m->name }}</div>
                                        @if ($providerName)
                                            <span class="mt-0.5 block text-xs text-faint">via {{ $providerName }}</span>
                                        @elseif ($onlyWorkspace && $connected > 0)
                                            <x-status-dot variant="ok" :label="$mappedCount > 0 ? $mappedCount.' '.Str::plural('client', $mappedCount).' mapped' : 'Connected'" class="mt-0.5" />
                                        @elseif ($connected > 0)
                                            <x-status-dot variant="ok" :label="$connected.' '.Str::plural('site', $connected).' connected'" class="mt-0.5" />
                                        @else
                                            <span class="mt-0.5 block text-xs text-faint">Not connected</span>
                                        @endif
                                    </div>
                                    @if ($ws && ! $onlyWorkspace)
                                        <span class="rounded-full bg-accent-soft px-2 py-0.5 text-[11px] font-medium" style="color:var(--color-accent)">Workspace</span>
                                    @endif
                                </div>
                                <p class="mt-3 flex-1 text-[13.5px] leading-relaxed text-muted">{{ $m->description }}</p>
                                @if ($connectUrl || ($onlyWorkspace && $canManage))
                                    <div class="mt-3 text-[12.5px] font-semibold" style="color:var(--color-accent)">
                                        @if ($providerName)
                                            Set up via {{ $providerName }}
                                        @elseif ($onlyWorkspace)
                                            {{ $connected > 0 ? 'Manage connection & mapped clients' : 'Connect for the whole workspace' }}
                                        @else
                                            {{ $connected > 0 ? 'Manage on a site' : 'Connect on a site' }}
                                        @endif →
                                    </div>
                                @endif
                            </a>
                            @if ($supportsWorkspace && ! $onlyWorkspace && $canManage)
                                <a href="{{ $workspaceUrl }}"
                                   wire:navigate
                                   class="flex items-center gap-1.5 px-1 text-[12px] text-muted transition hover:text-ink">
                                    <x-icon :name="$ws ? 'plug' : 'plus'" class="h-3 w-3" />
                                    {{ $ws ? 'Workspace connection — manage & map sites' : 'Or connect once for the whole workspace' }}
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    @can('manage-sites')
        @if ($siteCount === 0)
            <p class="mt-8 text-xs text-faint">Add a website under
                <a href="{{ route('sites.index') }}" wire:navigate class="font-semibold" style="color:var(--color-accent)">Sites</a> before connecting integrations.</p>
        @endif
    @endcan
</div>
