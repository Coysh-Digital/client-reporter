<div>
    <x-page-header title="Import sites" subtitle="Bring your fleet in from a platform you already use, or a CSV export." eyebrow="Portfolio">
        <x-slot:actions>
            <x-button :href="route('sites.index')">Back to sites</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($result)
        <x-alert variant="ok" class="mb-6">
            Imported {{ $result['created'] }} {{ Str::plural('site', $result['created']) }}{{ ($result['updated'] ?? 0) > 0 ? ', updated '.$result['updated'] : '' }}{{ $result['skipped'] > 0 ? ', skipped '.$result['skipped'].' already present' : '' }}.
            <x-slot:action><a href="{{ route('sites.index') }}" wire:navigate class="font-semibold underline">View sites</a></x-slot:action>
        </x-alert>
    @endif

    {{-- CMS → source → credentials --}}
    <div class="cr-panel mb-6">
        <div class="cr-panel-header"><h2 class="cr-eyebrow">What are you importing?</h2></div>
        <div class="space-y-6 px-5 py-5">
            {{-- Where the sites come from --}}
            <div>
                <div class="cr-label">Import from</div>
                <x-segmented :options="['platform' => 'A platform', 'csv' => 'A CSV file']" :value="$mode" model="mode" label="Import method" />
            </div>

            @if ($mode === 'csv')
                {{-- CSV upload --}}
                <div>
                    <x-field label="CSV file" for="import-csv" name="csv" :required="true"
                             help="A header row with a url column, plus optional name, client and cms columns. One site per row.">
                        <input type="file" id="import-csv" wire:model="csv" accept=".csv,text/csv,text/plain" class="cr-input">
                    </x-field>
                    <p class="mt-2 text-xs text-faint">
                        Example header: <code class="rounded bg-paper/60 px-1 py-0.5">url,name,client,cms</code>. A bare domain is treated as https, and any cms other than wordpress, craft or other is left unset.
                    </p>
                </div>

                @if ($error)
                    <x-alert variant="danger">{{ $error }}</x-alert>
                @endif

                <div>
                    <x-button variant="primary" wire:click="parseCsv" wire:target="csv,parseCsv" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="csv,parseCsv">Read file</span>
                        <span wire:loading wire:target="csv">Uploading…</span>
                        <span wire:loading wire:target="parseCsv">Reading…</span>
                    </x-button>
                </div>
            @else
            {{-- 1. CMS --}}
            <div>
                <div class="cr-label">CMS</div>
                @if (empty($cmsOptions))
                    <div class="rounded-lg border border-line bg-paper/50 px-4 py-3 text-sm text-muted">
                        No CMS integrations are enabled. Enable one under <a href="{{ route('integrations.index') }}" wire:navigate class="cr-link">Integrations</a> first.
                    </div>
                @else
                    <x-segmented :options="collect($cmsOptions)->pluck('name', 'key')->all()" :value="$cms" model="cms" label="CMS" />
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
                        <x-segmented :options="collect($importers)->mapWithKeys(fn ($imp) => [$imp->key() => $imp->label()])->all()" :value="$provider" model="provider" label="Import source" />
                    @endif
                </div>
            @endif

            {{-- 3. Credentials for the chosen source --}}
            @if (! empty($fields))
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($fields as $field)
                        <x-field :label="$field['label']" :for="'import-'.$field['name']" :name="'config.'.$field['name']" :required="(bool) ($field['required'] ?? false)" :help="$field['help'] ?? null"
                                 :class="($field['name'] ?? '') === 'dashboard_url' ? 'sm:col-span-2' : ''">
                            <input type="{{ $field['type'] ?? 'text' }}" id="import-{{ $field['name'] }}" wire:model="config.{{ $field['name'] }}"
                                   placeholder="{{ $field['placeholder'] ?? '' }}" class="cr-input" autocomplete="off">
                        </x-field>
                    @endforeach
                </div>

                @if ($error)
                    <x-alert variant="danger">{{ $error }}</x-alert>
                @endif

                <div>
                    <x-button variant="primary" wire:click="fetch">
                        <span wire:loading.remove wire:target="fetch">Fetch sites</span>
                        <span wire:loading wire:target="fetch">Fetching…</span>
                    </x-button>
                </div>
            @endif
            @endif
        </div>
    </div>

    {{-- Mapping --}}
    @if ($fetched && count($rows))
        <div class="cr-panel">
            @php $selectable = collect($rows)->reject(fn ($r) => $r['already'] && $duplicates === 'skip'); $selected = $selectable->filter(fn ($r) => $r['include'] ?? false)->count(); $matched = collect($rows)->filter(fn ($r) => $r['already'])->count(); @endphp
            <div class="cr-panel-header flex-wrap gap-3">
                <div>
                    <h2 class="cr-eyebrow">{{ count($rows) }} {{ Str::plural('site', count($rows)) }} found — map to clients</h2>
                    <p class="mt-0.5 text-xs text-faint">{{ $selected }} of {{ $selectable->count() }} selected{{ $matched > 0 ? ' · '.$matched.' already in your fleet' : '' }}{{ $ignoredRows > 0 ? ' · '.$ignoredRows.' '.Str::plural('row', $ignoredRows).' skipped (no valid URL)' : '' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($matched > 0)
                        <label for="dup-strategy" class="text-xs text-faint">Already in your fleet</label>
                        <select wire:model.live="duplicates" id="dup-strategy" class="cr-input text-sm">
                            <option value="skip">Skip</option>
                            <option value="update">Update details</option>
                            <option value="overwrite">Overwrite (incl. client)</option>
                        </select>
                    @endif
                    <x-button size="sm" variant="ghost" wire:click="selectAll(true)">Select all</x-button>
                    <x-button size="sm" variant="ghost" wire:click="selectAll(false)">Select none</x-button>
                    <x-button variant="primary" wire:click="import" :disabled="$selected === 0">
                        <span wire:loading.remove wire:target="import">Import selected</span>
                        <span wire:loading wire:target="import">Importing…</span>
                    </x-button>
                </div>
            </div>
            <div class="divide-y divide-line">
                @foreach ($rows as $i => $row)
                    <div class="flex flex-wrap items-center gap-4 px-5 py-3" wire:key="row-{{ $i }}">
                        <label class="flex min-w-0 flex-1 items-center gap-3">
                            <input type="checkbox" wire:model.live="rows.{{ $i }}.include" @disabled($row['already'] && $duplicates === 'skip') class="cr-checkbox">
                            <x-avatar :name="$row['name']" size="lg" aria-hidden="true" />
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-ink">{{ $row['name'] }}</span>
                                <span class="block truncate text-xs text-faint">{{ $row['host'] }}@if ($row['already']){{ $duplicates === 'skip' ? ' · already imported (skipped)' : ($duplicates === 'overwrite' ? ' · will be overwritten' : ' · will be updated') }}@endif</span>
                            </span>
                        </label>
                        <div class="flex items-center gap-2">
                            <label for="import-client-{{ $i }}" class="text-xs text-faint">Client</label>
                            <select wire:model.live="rows.{{ $i }}.client_choice" id="import-client-{{ $i }}" @disabled($row['already'] && $duplicates !== 'overwrite') class="cr-input w-52 text-sm">
                                <option value="new">＋ New client</option>
                                @foreach ($clients as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            @if (($row['client_choice'] ?? 'new') === 'new')
                                <input wire:model="rows.{{ $i }}.new_client_name" @disabled($row['already'] && $duplicates !== 'overwrite')
                                       placeholder="New client name" aria-label="New client name for {{ $row['name'] }}" class="cr-input w-48 text-sm">
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
