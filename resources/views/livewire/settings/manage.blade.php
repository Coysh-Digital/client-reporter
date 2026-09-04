<div>
    <x-page-header title="Settings" subtitle="Application-wide configuration for this Client Reporter install." eyebrow="Workspace">
        <x-slot:actions>
            <button wire:click="save" class="cr-btn cr-btn-primary">
                <span wire:loading.remove wire:target="save">Save changes</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-lg bg-ok-soft px-4 py-3 text-sm text-ok">{{ session('status') }}</div>
    @endif

    <div class="mx-auto max-w-3xl space-y-6">
        {{-- Settings tabs --}}
        <div class="flex gap-1 border-b border-line">
            <a href="{{ route('settings.edit') }}" wire:navigate class="border-b-2 px-3 py-2 text-sm font-semibold text-ink" style="border-color:var(--color-accent)">General</a>
            <a href="{{ route('settings.ai') }}" wire:navigate class="border-b-2 border-transparent px-3 py-2 text-sm text-muted hover:text-ink">AI summaries</a>
        </div>

        {{-- Software updates --}}
        <section class="cr-panel">
            <div class="border-b border-line px-5 py-3.5"><h2 class="cr-eyebrow">Software updates</h2></div>
            <div class="space-y-4 px-5 py-5">
                <label class="flex cursor-pointer items-center gap-3">
                    <input type="checkbox" wire:model="updates_enabled" class="peer sr-only">
                    <span class="relative h-5 w-9 shrink-0 rounded-full bg-line-strong transition peer-checked:bg-accent">
                        <span class="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white transition peer-checked:translate-x-4"></span>
                    </span>
                    <span class="text-sm text-ink">Check for Client Reporter updates</span>
                </label>
                <p class="text-xs text-muted">
                    Client Reporter never updates itself — it only tells administrators when a newer release is available.
                    You’re on <span class="tnum font-semibold text-ink">v{{ $version }}</span>@if ($update['latest'])
                        · latest is <span class="tnum font-semibold text-ink">v{{ $update['latest'] }}</span>@endif.
                </p>
            </div>
        </section>

        {{-- Report output --}}
        <section class="cr-panel">
            <div class="border-b border-line px-5 py-3.5"><h2 class="cr-eyebrow">Report output</h2></div>
            <div class="grid gap-4 px-5 py-5 sm:grid-cols-2">
                <div>
                    <label class="cr-label">PDF engine</label>
                    <select wire:model="pdf_driver" class="cr-input">
                        <option value="dompdf">dompdf — works on any shared host</option>
                        <option value="browsershot">Browsershot — pixel-perfect (needs Node/Chromium)</option>
                    </select>
                    @error('pdf_driver') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-faint">dompdf is safest on shared hosting; Browsershot renders exactly like the web report but needs a headless browser.</p>
                </div>
                <div>
                    <label class="cr-label">Default share-link expiry (days)</label>
                    <input type="number" min="1" max="3650" wire:model="default_share_expiry_days" placeholder="Never expires" class="cr-input">
                    @error('default_share_expiry_days') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-faint">Pre-fills the expiry when creating a public report link. Leave blank for no expiry.</p>
                </div>
            </div>
        </section>

        {{-- Data collection --}}
        <section class="cr-panel">
            <div class="border-b border-line px-5 py-3.5"><h2 class="cr-eyebrow">Data collection</h2></div>
            <div class="grid gap-4 px-5 py-5 sm:grid-cols-2">
                <div>
                    <label class="cr-label">Collection interval (minutes)</label>
                    <input type="number" min="15" max="10080" wire:model="collection_interval" class="cr-input">
                    @error('collection_interval') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-faint">How often the hourly collector re-pulls data for a connection (default 360 = every 6 hours).</p>
                </div>
                <div>
                    <label class="cr-label">Retention (days)</label>
                    <input type="number" min="1" max="3650" wire:model="collection_retention_days" placeholder="Keep everything" class="cr-input">
                    @error('collection_retention_days') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-faint">Prune collected metrics older than this. Generated reports keep their own snapshots, so they’re unaffected. Blank keeps everything.</p>
                </div>
            </div>
        </section>

        {{-- About --}}
        <section class="cr-panel">
            <div class="border-b border-line px-5 py-3.5"><h2 class="cr-eyebrow">About</h2></div>
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
