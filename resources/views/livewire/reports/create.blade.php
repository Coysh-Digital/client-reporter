<div>
    <x-page-header title="New report" subtitle="Choose a site, a period and a starting template." />

    <form wire:submit="save" class="cr-card max-w-xl px-6 py-6 space-y-5">
        <div>
            <label class="cr-label">Site</label>
            <select wire:model="site_id" class="cr-input" required>
                <option value="">Select a site…</option>
                @foreach ($this->sites() as $site)
                    <option value="{{ $site->id }}">{{ $site->client->name }} — {{ $site->name }}</option>
                @endforeach
            </select>
            @error('site_id') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="cr-label">Report title</label>
            <input wire:model="title" class="cr-input" placeholder="Monthly website report" required>
            @error('title') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="cr-label">Template</label>
            <select wire:model="report_template_id" class="cr-input">
                <option value="">Blank (cover, intro, overview, closing)</option>
                @foreach ($this->templates() as $template)
                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="cr-label">Period</label>
                <select wire:model.live="preset" class="cr-input">
                    @foreach (\App\Support\DateRange::presets() as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="cr-label">From</label>
                <input type="date" wire:model="range_start" class="cr-input">
            </div>
            <div>
                <label class="cr-label">To</label>
                <input type="date" wire:model="range_end" class="cr-input">
            </div>
        </div>
        @error('range_end') <p class="text-xs text-danger">{{ $message }}</p> @enderror

        <label class="flex items-center gap-2 text-sm text-muted">
            <input type="checkbox" wire:model="compare_previous" class="rounded border-line-strong text-accent focus:ring-accent">
            Compare with the previous period
        </label>

        <div class="flex items-center gap-3 border-t border-line pt-5">
            <button type="submit" class="cr-btn cr-btn-primary">Create &amp; build</button>
            <a href="{{ route('reports.index') }}" wire:navigate class="cr-btn cr-btn-secondary">Cancel</a>
        </div>
    </form>
</div>
