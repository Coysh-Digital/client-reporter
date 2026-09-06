<div>
    <x-breadcrumbs :items="[['label' => 'Reports', 'href' => route('reports.index')], ['label' => 'New report']]" />
    <x-page-header title="New report" subtitle="Choose a site, a period and a starting template." />

    <form wire:submit="save" class="cr-card max-w-xl px-6 py-6 space-y-5">
        <x-field label="Site" for="site_id" required>
            <select wire:model="site_id" id="site_id" class="cr-input" required>
                <option value="">Select a site…</option>
                @foreach ($this->sites() as $site)
                    <option value="{{ $site->id }}">{{ $site->client->name }} — {{ $site->name }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field label="Report title" for="title" required>
            <input wire:model="title" id="title" class="cr-input" placeholder="Monthly website report" required>
        </x-field>

        <x-field label="Template" for="report_template_id">
            <select wire:model="report_template_id" id="report_template_id" class="cr-input">
                <option value="">Blank (cover, intro, overview, closing)</option>
                @foreach ($this->templates() as $template)
                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                @endforeach
            </select>
        </x-field>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-field label="Period" for="preset" help="Choosing a period fills in the dates.">
                <select wire:model.live="preset" id="preset" class="cr-input">
                    @foreach (\App\Support\DateRange::presets() as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="From" for="range_start">
                <input type="date" wire:model="range_start" id="range_start" class="cr-input">
            </x-field>
            <x-field label="To" for="range_end">
                <input type="date" wire:model="range_end" id="range_end" class="cr-input">
            </x-field>
        </div>

        <x-checkbox wire:model="compare_previous" id="compare_previous" label="Compare with the previous period" />

        <div class="flex items-center gap-3 border-t border-line pt-5">
            <x-button type="submit" variant="primary">Create &amp; build</x-button>
            <x-button :href="route('reports.index')">Cancel</x-button>
        </div>
    </form>
</div>
