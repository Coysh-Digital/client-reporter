<div>
    <x-page-header title="Sites" subtitle="Every website you monitor and report on." eyebrow="Portfolio">
        <x-slot:actions>
            @can('manage-sites')
                <x-button :href="route('sites.import')" icon="file-import">Import sites</x-button>
                <x-button variant="primary" :href="route('sites.create')" icon="plus">New site</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <label class="sr-only" for="sites-search">Search sites or clients</label>
        <input wire:model.live.debounce.300ms="search" id="sites-search" type="search" placeholder="Search sites or clients…" class="cr-input max-w-xs">
        <x-segmented :options="['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive']" :value="$status" action="setStatus" label="Filter sites by status" />
        <label class="sr-only" for="sites-client">Client</label>
        <select wire:model.live="client" id="sites-client" class="cr-input w-auto py-1.5 pr-8 text-sm">
            <option value="">All clients</option>
            @foreach ($clients as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>
        @if ($cmsTypes !== [])
            <label class="sr-only" for="sites-cms">CMS</label>
            <select wire:model.live="cms" id="sites-cms" class="cr-input w-auto py-1.5 pr-8 text-sm">
                <option value="">Any CMS</option>
                @foreach ($cmsTypes as $type)
                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                @endforeach
            </select>
        @endif
        <label class="ml-auto flex shrink-0 items-center gap-2 text-xs text-muted" for="sites-per-page">
            <span>Show</span>
            <select wire:model.live="perPage" id="sites-per-page" class="cr-input w-auto py-1.5 pr-8 text-sm">
                @foreach (\App\Livewire\Sites\Index::PER_PAGE_OPTIONS as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
            <span>per page</span>
        </label>
    </div>

    @if ($sites->isEmpty())
        @if ($search !== '' || $status !== 'all' || $client !== null || $cms !== '')
            <x-empty-state icon="magnifying-glass" title="No sites match" description="Try a different search or clear the filters.">
                <x-slot:action>
                    <x-button wire:click="$set('search', ''); $set('status', 'all'); $set('client', null); $set('cms', '')">Clear filters</x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            <x-empty-state icon="globe" title="No sites yet" description="Sites belong to a client. Create a client first, then add their websites.">
                @can('manage-sites')
                    <x-slot:action>
                        <x-button variant="primary" :href="route('sites.create')" icon="plus">New site</x-button>
                        <x-button :href="route('sites.import')" icon="file-import" class="ml-2">Import sites</x-button>
                    </x-slot:action>
                @endcan
            </x-empty-state>
        @endif
    @else
        <x-table caption="Sites">
            <thead>
                <tr>
                    <x-th sort="name" :current="$this->currentSort()" :direction="$this->currentDirection()">Site</x-th>
                    <x-th sort="client" :current="$this->currentSort()" :direction="$this->currentDirection()">Client</x-th>
                    <x-th sort="cms" :current="$this->currentSort()" :direction="$this->currentDirection()">CMS</x-th>
                    <x-th>Health</x-th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sites as $site)
                    @php $siteHealth = $site->is_active ? ($health[$site->id] ?? null) : null; @endphp
                    <tr wire:key="site-{{ $site->id }}">
                        <x-td>
                            <a href="{{ route('sites.show', $site) }}" wire:navigate class="flex min-w-0 items-center gap-3">
                                <x-avatar :name="$site->name" size="lg" :icon="$site->faviconUrl()" aria-hidden="true" />
                                <span class="min-w-0">
                                    <span class="block truncate text-md font-semibold text-ink">{{ $site->name }}</span>
                                    <span class="block truncate text-xs text-faint">{{ $site->host() }}</span>
                                </span>
                            </a>
                        </x-td>
                        <x-td><a href="{{ route('clients.show', $site->client) }}" wire:navigate class="block max-w-[14rem] truncate text-muted hover:text-ink">{{ $site->client->name }}</a></x-td>
                        <x-td nowrap>
                            @if ($site->cms_type)
                                <x-badge variant="neutral">{{ ucfirst($site->cms_type) }}</x-badge>
                            @else
                                <span class="text-faint">—</span>
                            @endif
                        </x-td>
                        <x-td nowrap>
                            @if (! $site->is_active)
                                <x-status-dot variant="neutral" label="Inactive" />
                            @elseif ($siteHealth)
                                <x-status-dot :variant="$siteHealth->badge()" :label="$siteHealth->label()" />
                            @else
                                <x-status-dot variant="ok" label="Healthy" />
                            @endif
                        </x-td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        <div class="mt-4">{{ $sites->links('vendor.pagination.cr') }}</div>
    @endif
</div>
