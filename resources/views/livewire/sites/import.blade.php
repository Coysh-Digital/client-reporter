<div>
    <x-page-header title="Import sites" subtitle="Bring your fleet in from a platform you already use." eyebrow="Portfolio">
        <x-slot:actions>
            <a href="{{ route('sites.index') }}" wire:navigate class="cr-btn cr-btn-secondary">Back to sites</a>
        </x-slot:actions>
    </x-page-header>

    @if ($result)
        <div class="mb-6 rounded-lg bg-ok-soft px-4 py-3 text-sm text-ok">
            Imported {{ $result['created'] }} {{ Str::plural('site', $result['created']) }}{{ $result['skipped'] > 0 ? ', skipped '.$result['skipped'].' already present' : '' }}.
            <a href="{{ route('sites.index') }}" wire:navigate class="font-semibold underline">View sites</a>
        </div>
    @endif

    {{-- CMS → source → credentials --}}
    <div class="cr-panel mb-6">
        <div class="border-b border-line px-5 py-3.5"><h2 class="cr-eyebrow">What are you importing?</h2></div>
        <div class="space-y-6 px-5 py-5">
            {{-- 1. CMS --}}
            <div>
                <div class="cr-label">CMS</div>
                @if (empty($cmsOptions))
                    <div class="rounded-lg border border-line bg-paper/50 px-4 py-3 text-sm text-muted">
                        No CMS integrations are enabled. Enable one under <a href="{{ route('integrations.index') }}" wire:navigate class="font-semibold" style="color:var(--color-accent)">Integrations</a> first.
                    </div>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($cmsOptions as $option)
                            <button type="button" wire:click="$set('cms', '{{ $option['key'] }}')"
                                    @class([
                                        'rounded-lg border px-4 py-2 text-sm font-medium transition',
                                        'border-accent bg-accent-soft text-accent' => $cms === $option['key'],
                                        'border-line text-muted hover:border-line-strong' => $cms !== $option['key'],
                                    ])>{{ $option['name'] }}</button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- 2. Source (importers for the chosen CMS) --}}
            @if (! empty($cmsOptions))
                <div>
                    <div class="cr-label">Source</div>
                    @if (empty($importers))
                        <div class="rounded-lg border border-line bg-paper/50 px-4 py-4 text-sm text-muted">
                            There are no import sources for {{ $currentCmsName }} yet.
                        </div>
                    @else
                        <div class="flex flex-wrap gap-2">
                            @foreach ($importers as $imp)
                                <button type="button" wire:click="$set('provider', '{{ $imp->key() }}')"
                                        @class([
                                            'rounded-lg border px-4 py-2 text-sm font-medium transition',
                                            'border-accent bg-accent-soft text-accent' => $provider === $imp->key(),
                                            'border-line text-muted hover:border-line-strong' => $provider !== $imp->key(),
                                        ])>{{ $imp->label() }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            {{-- 3. Credentials for the chosen source --}}
            @if (! empty($fields))
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($fields as $field)
                        <div @class(['sm:col-span-2' => ($field['name'] ?? '') === 'dashboard_url'])>
                            <label class="cr-label">{{ $field['label'] }}<span style="color:var(--color-danger)">{{ ($field['required'] ?? false) ? ' *' : '' }}</span></label>
                            <input type="{{ $field['type'] ?? 'text' }}" wire:model="config.{{ $field['name'] }}"
                                   placeholder="{{ $field['placeholder'] ?? '' }}" class="cr-input" autocomplete="off">
                            @if (! empty($field['help']))
                                <p class="mt-1 text-xs text-faint">{{ $field['help'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($error)
                    <div class="rounded-lg bg-danger-soft px-4 py-3 text-sm text-danger">{{ $error }}</div>
                @endif

                <div>
                    <button wire:click="fetch" class="cr-btn cr-btn-primary">
                        <span wire:loading.remove wire:target="fetch">Fetch sites</span>
                        <span wire:loading wire:target="fetch">Fetching…</span>
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Mapping --}}
    @if ($fetched && count($rows))
        <div class="cr-panel">
            <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
                <h2 class="cr-eyebrow">{{ count($rows) }} {{ Str::plural('site', count($rows)) }} found — map to clients</h2>
                <button wire:click="import" class="cr-btn cr-btn-primary">
                    <span wire:loading.remove wire:target="import">Import selected</span>
                    <span wire:loading wire:target="import">Importing…</span>
                </button>
            </div>
            <div class="divide-y divide-line">
                @foreach ($rows as $i => $row)
                    <div class="flex flex-wrap items-center gap-4 px-5 py-3" wire:key="row-{{ $i }}">
                        <label class="flex min-w-0 flex-1 items-center gap-3">
                            <input type="checkbox" wire:model.live="rows.{{ $i }}.include" @disabled($row['already'])
                                   class="rounded border-line-strong text-accent focus:ring-accent">
                            <x-avatar :name="$row['name']" size="lg" />
                            <span class="min-w-0">
                                <span class="block truncate text-[14px] font-semibold text-ink">{{ $row['name'] }}</span>
                                <span class="block truncate text-[12.5px] text-faint">{{ $row['host'] }}{{ $row['already'] ? ' · already imported' : '' }}</span>
                            </span>
                        </label>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-faint">Client</span>
                            <select wire:model.live="rows.{{ $i }}.client_choice" @disabled($row['already']) class="cr-input w-52 text-sm">
                                <option value="new">＋ New client</option>
                                @foreach ($clients as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            @if (($row['client_choice'] ?? 'new') === 'new')
                                <input wire:model="rows.{{ $i }}.new_client_name" @disabled($row['already'])
                                       placeholder="New client name" class="cr-input w-48 text-sm">
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
