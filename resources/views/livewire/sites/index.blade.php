<div>
    <x-page-header title="Sites" subtitle="Every website you monitor and report on." eyebrow="Portfolio">
        <x-slot:actions>
            @can('manage-sites')
                <a href="{{ route('sites.import') }}" wire:navigate class="cr-btn cr-btn-secondary">
                    <x-icon name="file-import" class="h-3.5 w-3.5" />
                    Import sites
                </a>
                <a href="{{ route('sites.create') }}" wire:navigate class="cr-btn cr-btn-primary">
                    <x-icon name="plus" class="h-3.5 w-3.5" />
                    New site
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex items-center justify-between gap-3">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search sites or clients…" class="cr-input max-w-xs">
        <label class="flex shrink-0 items-center gap-2 text-[12.5px] text-muted">
            <span class="hidden sm:inline">Show</span>
            <select wire:model.live="perPage" class="cr-input w-auto py-1.5 pr-8 text-sm">
                @foreach (\App\Livewire\Sites\Index::PER_PAGE_OPTIONS as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
            <span class="hidden sm:inline">per page</span>
        </label>
    </div>

    @if ($sites->isEmpty())
        <x-empty-state icon="globe" title="No sites yet" description="Sites belong to a client. Create a client first, then add their websites." />
    @else
        <div class="cr-panel">
            <div class="hidden items-center gap-4 border-b border-line px-5 py-2.5 text-[10.5px] font-bold uppercase tracking-[0.07em] text-faint sm:grid sm:grid-cols-[minmax(0,1fr)_180px_120px_130px]">
                <span>Site</span><span>Client</span><span>CMS</span><span>Health</span>
            </div>
            @foreach ($sites as $site)
                @php $siteHealth = $site->is_active ? ($health[$site->id] ?? null) : null; @endphp
                <div wire:key="site-{{ $site->id }}"
                     class="flex flex-col gap-3 px-5 py-3.5 hover:bg-paper sm:grid sm:grid-cols-[minmax(0,1fr)_180px_120px_130px] sm:items-center sm:gap-4"
                     @if (! $loop->last) style="border-bottom:1px solid var(--color-line);" @endif>
                    <a href="{{ route('sites.show', $site) }}" wire:navigate class="flex min-w-0 items-center gap-3">
                        <x-avatar :name="$site->name" size="lg" :icon="$site->faviconUrl()" />
                        <span class="min-w-0">
                            <span class="block truncate text-[14.5px] font-semibold text-ink">{{ $site->name }}</span>
                            <span class="block truncate text-[12.5px] text-faint">{{ $site->host() }}</span>
                        </span>
                    </a>
                    <div class="flex flex-wrap items-center justify-between gap-3 sm:contents">
                        <a href="{{ route('clients.show', $site->client) }}" wire:navigate class="truncate text-[13px] text-muted hover:text-ink">{{ $site->client->name }}</a>
                        <span>
                            @if ($site->cms_type)
                                <x-badge variant="neutral">{{ ucfirst($site->cms_type) }}</x-badge>
                            @else
                                <span class="text-faint">—</span>
                            @endif
                        </span>
                        <span>
                            @if (! $site->is_active)
                                <x-status-dot variant="neutral" label="Inactive" />
                            @elseif ($siteHealth)
                                <x-status-dot :variant="$siteHealth->badge()" :label="$siteHealth->label()" />
                            @else
                                <x-status-dot variant="ok" label="Healthy" />
                            @endif
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $sites->links('vendor.pagination.cr') }}</div>
    @endif
</div>
