<div>
    <x-page-header title="Report templates" subtitle="Reusable section layouts you can apply to any new report." eyebrow="Portfolio">
        <x-slot:actions>
            <x-button variant="primary" :href="route('templates.create')" icon="plus">New template</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4">
        <label class="sr-only" for="templates-search">Search templates</label>
        <input wire:model.live.debounce.300ms="search" id="templates-search" type="search" placeholder="Search templates…" class="cr-input max-w-xs">
    </div>

    @if ($templates->isEmpty())
        @if ($search !== '')
            <x-empty-state icon="magnifying-glass" title="No templates match" description="Try a different search.">
                <x-slot:action><x-button wire:click="$set('search', '')">Clear search</x-button></x-slot:action>
            </x-empty-state>
        @else
            <x-empty-state icon="layer-group" title="No templates yet"
                           description="Create a template to reuse a set of sections — cover, analytics, uptime and more — across your clients’ reports.">
                <x-slot:action>
                    <x-button variant="primary" :href="route('templates.create')" icon="plus">New template</x-button>
                </x-slot:action>
            </x-empty-state>
        @endif
    @else
        <x-table caption="Report templates">
            <thead>
                <tr>
                    <x-th sort="name" :current="$this->currentSort()" :direction="$this->currentDirection()">Template</x-th>
                    <x-th>Sections</x-th>
                    <x-th sort="sites" :current="$this->currentSort()" :direction="$this->currentDirection()">Used by</x-th>
                    <x-th sort="updated" :current="$this->currentSort()" :direction="$this->currentDirection()">Updated</x-th>
                    <x-th><span class="sr-only">Actions</span></x-th>
                </tr>
            </thead>
            <tbody>
                @foreach ($templates as $template)
                    <tr wire:key="tpl-{{ $template->id }}">
                        <x-td>
                            <a href="{{ route('templates.edit', $template) }}" wire:navigate class="block min-w-0">
                                <span class="block truncate text-md font-semibold text-ink">{{ $template->name }}</span>
                                @if ($template->description)
                                    <span class="block truncate text-xs text-faint">{{ $template->description }}</span>
                                @endif
                            </a>
                        </x-td>
                        <x-td nowrap><span class="tnum text-muted">{{ count($template->blocks) }} {{ Str::plural('section', count($template->blocks)) }}</span></x-td>
                        <x-td nowrap><span class="tnum text-muted">{{ $template->sites_count }} scheduled {{ Str::plural('site', $template->sites_count) }}</span></x-td>
                        <x-td nowrap><span class="text-xs text-muted">{{ $template->updated_at?->diffForHumans() ?? '—' }}</span></x-td>
                        <x-td align="right" nowrap>
                            <x-dropdown>
                                <x-slot:trigger>
                                    <button type="button" class="cr-btn-icon" aria-label="Actions for {{ $template->name }}">
                                        <x-icon name="ellipsis-horizontal" class="h-4 w-4" />
                                    </button>
                                </x-slot:trigger>
                                <x-dropdown-item :href="route('templates.edit', $template)" icon="pencil-square">Edit</x-dropdown-item>
                                <button type="button" role="menuitem" class="cr-menu-item" wire:click="duplicate({{ $template->id }})" x-on:click="close()">
                                    <x-icon name="document-duplicate" class="h-3.5 w-3.5 shrink-0 text-faint" /> Duplicate
                                </button>
                                <div class="cr-menu-separator"></div>
                                <x-confirm-button role="menuitem" class="cr-menu-item cr-menu-item-danger"
                                    action="delete({{ $template->id }})"
                                    title="Delete the “{{ $template->name }}” template?"
                                    message="{{ $template->sites_count > 0 ? $template->sites_count.' scheduled '.Str::plural('site', $template->sites_count).' will fall back to the default sections.' : 'Existing reports are not affected.' }}"
                                    confirm="Delete template" :danger="true">
                                    <x-icon name="trash-can" class="h-3.5 w-3.5 shrink-0" /> Delete
                                </x-confirm-button>
                            </x-dropdown>
                        </x-td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        <div class="mt-4">{{ $templates->links('vendor.pagination.cr') }}</div>
    @endif
</div>
