<div>
    <x-page-header title="Integrations" subtitle="The services Client Reporter can pull data from. Connect them per site, or once for the whole workspace." eyebrow="Setup">
        @can('manage-integrations')
            <x-slot:actions>
                <x-button :href="config('client-reporter.docs.integrations')" :navigate="false" target="_blank" rel="noopener" icon="arrow-up-right-from-square">Build your own</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <label class="sr-only" for="integrations-search">Search integrations</label>
        <input wire:model.live.debounce.300ms="search" id="integrations-search" type="search" placeholder="Search integrations…" class="cr-input max-w-xs">
        <x-segmented :options="['' => 'All'] + collect(\App\Integrations\Support\IntegrationCategory::ordered())->filter(fn ($c) => isset($allGrouped[$c->value]))->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()"
                     :value="$category" action="setCategory" label="Filter by category" class="flex-wrap" />
    </div>

    @if ($grouped === [])
        <x-empty-state icon="magnifying-glass" title="No integrations match" description="Try a different search or category.">
            <x-slot:action><x-button wire:click="$set('search', ''); $set('category', '')">Clear filters</x-button></x-slot:action>
        </x-empty-state>
    @endif

    <div class="space-y-8">
        @foreach ($grouped as $category => $integrations)
            @php $catConnected = collect($integrations)->sum(fn ($i) => (int) ($health[$i->manifest()->key]->total ?? 0)); @endphp
            <section>
                <div class="mb-3 flex items-center gap-2">
                    <h2 class="cr-eyebrow">{{ \App\Integrations\Support\IntegrationCategory::from($category)->label() }}</h2>
                    @if ($catConnected > 0)
                        <span class="tnum text-xs text-faint">{{ $catConnected }} connected</span>
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
                                $provider = collect($allGrouped)->flatten()->first(fn ($i) => $i->manifest()->key === $m->providedBy);
                                $providerName = $provider?->manifest()->name ?? $m->providedBy;
                                $connectKey = $m->providedBy;
                            }
                            $canManage = auth()->user()->can('manage-integrations') && $siteCount > 0;
                            $supportsWorkspace = $integration->supportsWorkspaceScope();
                            $onlyWorkspace = $integration->onlyWorkspaceScope();
                            $ws = $workspace[$m->key] ?? null;
                            $mappedCount = $ws ? ($billingMappedCounts[$ws->id] ?? 0) : 0;
                            $row = $m->providedBy ? null : ($health[$m->key] ?? null);
                            $connected = $onlyWorkspace ? ($ws?->status?->value === 'connected' ? 1 : 0) : (int) ($row->total ?? 0);
                            $troubled = (int) ($row->troubled ?? 0);
                            // Direct connect target: the one site's connect screen, else the picker
                            // dialog. Skipped for workspace-only integrations (billing).
                            $connectUrl = ($canManage && ! $onlyWorkspace && $singleSite)
                                ? route('sites.integrations.connect', ['site' => $singleSite, 'key' => $connectKey])
                                : null;
                            $opensPicker = $canManage && ! $onlyWorkspace && ! $singleSite;
                            $workspaceUrl = $ws ? route('integrations.workspace.edit', $ws) : route('integrations.workspace.connect', $m->key);
                        @endphp
                        <div wire:key="cat-{{ $m->key }}" class="flex flex-col gap-1.5">
                            @php $tag = ($connectUrl || $opensPicker || ($onlyWorkspace && $canManage)) ? 'a' : 'div'; @endphp
                            <{{ $tag }}
                               @if ($connectUrl) href="{{ $connectUrl }}" wire:navigate
                               @elseif ($opensPicker) href="#" x-on:click.prevent="$dispatch('open-site-picker', { key: @js($connectKey), name: @js($m->name) })"
                               @elseif ($onlyWorkspace && $canManage) href="{{ $workspaceUrl }}" wire:navigate @endif
                               @class([
                                   'cr-panel flex flex-1 flex-col px-5 py-4',
                                   'transition hover:border-line-strong hover:shadow-sm' => $tag === 'a',
                               ])>
                                <div class="flex items-start gap-3">
                                    <x-avatar :name="$m->name" size="lg" :icon="$m->iconUrl()" aria-hidden="true" />
                                    <div class="min-w-0 flex-1">
                                        <div class="text-md font-semibold text-ink">{{ $m->name }}</div>
                                        @if ($providerName)
                                            <span class="mt-0.5 block text-xs text-faint">via {{ $providerName }}</span>
                                        @elseif ($onlyWorkspace && $connected > 0)
                                            <x-status-dot variant="ok" :label="$mappedCount > 0 ? $mappedCount.' '.Str::plural('client', $mappedCount).' mapped' : 'Connected'" class="mt-0.5" />
                                        @elseif ($troubled > 0)
                                            <x-status-dot variant="danger" :label="$troubled.' of '.$connected.' '.Str::plural('site', $connected).' '.($troubled === 1 ? 'needs' : 'need').' attention'" class="mt-0.5" />
                                        @elseif ($connected > 0)
                                            <x-status-dot variant="ok" :label="$connected.' '.Str::plural('site', $connected).' connected'" class="mt-0.5" />
                                        @else
                                            <span class="mt-0.5 block text-xs text-faint">Not connected</span>
                                        @endif
                                    </div>
                                    @if ($ws && ! $onlyWorkspace)
                                        <x-badge variant="accent">Workspace</x-badge>
                                    @endif
                                </div>
                                <p class="mt-3 flex-1 text-sm leading-relaxed text-muted">{{ $m->description }}</p>
                                @if ($tag === 'a')
                                    <div class="mt-3 text-xs font-semibold" style="color:var(--color-accent)">
                                        @if ($providerName)
                                            Set up via {{ $providerName }}
                                        @elseif ($onlyWorkspace)
                                            {{ $connected > 0 ? 'Manage connection & mapped clients' : 'Connect for the whole workspace' }}
                                        @else
                                            {{ $connected > 0 ? 'Manage on a site' : 'Connect on a site' }}
                                        @endif →
                                    </div>
                                @endif
                            </{{ $tag }}>
                            @if ($supportsWorkspace && ! $onlyWorkspace && $canManage)
                                <a href="{{ $workspaceUrl }}" wire:navigate class="flex items-center gap-1.5 px-1 text-xs text-muted transition hover:text-ink">
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
            <x-alert variant="info" class="mt-8">
                Add a website before connecting integrations.
                <x-slot:action><x-button size="sm" :href="route('sites.create')" icon="plus">New site</x-button></x-slot:action>
            </x-alert>
        @endif
    @endcan

    @if ($sites->isNotEmpty())
        {{-- Site picker: which site should this integration be connected on? --}}
        <div x-data="{ key: '', name: '', q: '' }" x-on:open-site-picker.window="key = $event.detail.key; name = $event.detail.name; q = ''; $nextTick(() => $dispatch('open-site-picker-dialog'))">
            <x-dialog name="site-picker-dialog" title="Connect on which site?" description="Pick the website this connection belongs to.">
                <label for="site-picker-search" class="sr-only">Filter sites</label>
                <input type="search" id="site-picker-search" x-model="q" placeholder="Filter sites…" class="cr-input mb-3">
                <ul class="max-h-80 divide-y divide-line overflow-y-auto">
                    @foreach ($sites as $site)
                        <li x-show="{{ Js::from(mb_strtolower($site->name.' '.$site->client->name.' '.$site->host())) }}.includes(q.toLowerCase())">
                            <a x-bind:href="'{{ route('sites.integrations.connect', ['site' => $site, 'key' => '__KEY__']) }}'.replace('__KEY__', key)"
                               class="flex items-center gap-3 px-1 py-2 text-sm transition hover:bg-paper">
                                <x-avatar :name="$site->name" :icon="$site->faviconUrl()" aria-hidden="true" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium text-ink">{{ $site->name }}</span>
                                    <span class="block truncate text-xs text-faint">{{ $site->client->name }} · {{ $site->host() }}</span>
                                </span>
                                <x-icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-faint" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-dialog>
        </div>
    @endif
</div>
