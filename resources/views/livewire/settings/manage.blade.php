<div>
    <x-page-header title="Settings" subtitle="Application-wide configuration for this Client Reporter install." eyebrow="Workspace">
        <x-slot:actions>
            <x-button variant="primary" wire:click="save">
                <span wire:loading.remove wire:target="save">Save changes</span>
                <span wire:loading wire:target="save">Saving…</span>
            </x-button>
        </x-slot:actions>
    </x-page-header>


    <div class="mx-auto max-w-3xl space-y-6">
        <x-tabs label="Settings sections" :items="[
            ['label' => 'General', 'href' => route('settings.edit'), 'active' => true],
            ['label' => 'AI summaries', 'href' => route('settings.ai')],
        ]" />

        {{-- Software updates --}}
        <section class="cr-panel">
            <div class="cr-panel-header"><h2 class="cr-eyebrow">Software updates</h2></div>
            <div class="space-y-4 px-5 py-5">
                <x-toggle wire:model="updates_enabled" label="Check for Client Reporter updates" />
                <p class="text-xs text-muted">
                    Client Reporter never updates itself — it only tells administrators when a newer release is available.
                    You’re on <span class="tnum font-semibold text-ink">v{{ $version }}</span>@if ($update['latest'])
                        · latest is <span class="tnum font-semibold text-ink">v{{ $update['latest'] }}</span>@endif.
                </p>
            </div>
        </section>

        {{-- Report output --}}
        <section class="cr-panel">
            <div class="cr-panel-header"><h2 class="cr-eyebrow">Report output</h2></div>
            <div class="grid gap-4 px-5 py-5 sm:grid-cols-2">
                <x-field label="PDF engine" for="pdf_driver" help="dompdf is safest on shared hosting; Browsershot renders exactly like the web report but needs a headless browser.">
                    <select wire:model="pdf_driver" id="pdf_driver" class="cr-input">
                        <option value="dompdf">dompdf — works on any shared host</option>
                        <option value="browsershot">Browsershot — pixel-perfect (needs Node/Chromium)</option>
                    </select>
                </x-field>
                <x-field label="Default share-link expiry (days)" for="default_share_expiry_days" help="Pre-fills the expiry when creating a public report link. Leave blank for no expiry.">
                    <input type="number" min="1" max="3650" wire:model="default_share_expiry_days" id="default_share_expiry_days" placeholder="Never expires" class="cr-input">
                </x-field>
            </div>
        </section>

        {{-- Data collection --}}
        <section class="cr-panel">
            <div class="cr-panel-header"><h2 class="cr-eyebrow">Data collection</h2></div>
            <div class="grid gap-4 px-5 py-5 sm:grid-cols-2">
                <x-field label="Collection interval (minutes)" for="collection_interval" help="How often the hourly collector re-pulls data for a connection (default 360 = every 6 hours).">
                    <input type="number" min="15" max="10080" wire:model="collection_interval" id="collection_interval" class="cr-input">
                </x-field>
                <x-field label="Retention (days)" for="collection_retention_days" help="Prune collected metrics older than this. Generated reports keep their own snapshots, so they’re unaffected. Collector run history is kept for 90 days and the last five renders of each report are kept regardless. Blank keeps everything.">
                    <input type="number" min="1" max="3650" wire:model="collection_retention_days" id="collection_retention_days" placeholder="Keep everything" class="cr-input">
                </x-field>
            </div>
        </section>

        {{-- About --}}
        <section class="cr-panel">
            <div class="cr-panel-header"><h2 class="cr-eyebrow">About</h2></div>
            <div class="px-5 py-2">
                <div class="flex justify-between border-b border-line py-2.5 text-sm">
                    <span class="text-muted">Application</span><span class="font-medium text-ink">{{ $appName }}</span>
                </div>
                <div class="flex justify-between border-b border-line py-2.5 text-sm">
                    <span class="text-muted">Version</span><span class="tnum font-medium text-ink">v{{ $version }}</span>
                </div>
                @if ($repository)
                    <div class="flex justify-between border-b border-line py-2.5 text-sm">
                        <span class="text-muted">Repository</span>
                        <a href="https://github.com/{{ $repository }}" target="_blank" rel="noopener" class="font-medium" style="color:var(--color-accent)">{{ $repository }}</a>
                    </div>
                @endif
                <div class="flex justify-between py-2.5 text-sm">
                    <span class="text-muted">Installed</span>
                    <span class="tnum font-medium text-ink">{{ $installedAt ? \Illuminate\Support\Carbon::parse($installedAt)->isoFormat('D MMM YYYY') : '—' }}</span>
                </div>
            </div>
        </section>
    </div>
</div>
