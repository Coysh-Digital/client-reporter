<div>
    <x-breadcrumbs :items="[['label' => 'Sites', 'href' => route('sites.index')], ['label' => $site?->name ?? 'New site']]" />
    <x-page-header :title="$site ? 'Edit site' : 'New site'"
                   :subtitle="$site?->name ?? 'Add a website to a client.'" />

    <form wire:submit="save" class="cr-card max-w-xl px-6 py-6 space-y-5">
        <x-field label="Client" for="client_id">
            <select wire:model="client_id" id="client_id" class="cr-input" @disabled($site)>
                <option value="">Select a client…</option>
                @foreach ($this->clients() as $clientOption)
                    <option value="{{ $clientOption->id }}">{{ $clientOption->name }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field label="Website URL" for="url" required>
            <input wire:model.blur="url" id="url" type="url" placeholder="https://example.com" class="cr-input" required>
        </x-field>

        <x-field label="Site name" for="name" required help="Suggested from the URL — edit to taste.">
            <input wire:model="name" id="name" type="text" class="cr-input" required>
        </x-field>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-field label="CMS" for="cms_type">
                <select wire:model="cms_type" id="cms_type" class="cr-input">
                    <option value="">Unknown</option>
                    <option value="wordpress">WordPress</option>
                    <option value="craft">Craft CMS</option>
                    <option value="other">Other</option>
                </select>
            </x-field>
            <x-field label="Environment" for="environment">
                <select wire:model="environment" id="environment" class="cr-input">
                    <option value="production">Production</option>
                    <option value="staging">Staging</option>
                    <option value="development">Development</option>
                </select>
            </x-field>
            <div>
                <span class="cr-label">Status</span>
                <div class="flex h-[38px] items-center">
                    <x-checkbox wire:model="is_active" id="is_active" label="Active" />
                </div>
            </div>
        </div>

        <x-field label="Reporting timezone" for="timezone">
            <select wire:model="timezone" id="timezone" class="cr-input">
                @foreach ($this->timezones() as $tz)
                    <option value="{{ $tz }}">{{ $tz }}</option>
                @endforeach
            </select>
        </x-field>

        <div class="border-t border-line pt-5">
            <div class="cr-eyebrow" style="color:var(--color-secondary);">Reporting schedule</div>
            <p class="mt-1 text-xs text-faint">Optional. When set, a report is generated automatically once each period closes, ready for you to review and send. Leave off for sites you report on manually.</p>

            <div class="mt-3 grid gap-5 sm:grid-cols-2">
                <x-field label="Frequency" for="report_frequency">
                    <select wire:model.live="report_frequency" id="report_frequency" class="cr-input">
                        @foreach ($this->frequencies() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>

                @if ($report_frequency !== 'none')
                    <x-field label="Report template" for="report_template_id" optional>
                        <select wire:model="report_template_id" id="report_template_id" class="cr-input">
                            <option value="">Default sections</option>
                            @foreach ($this->templates() as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif
            </div>

            @if ($report_frequency !== 'none')
                <div class="mt-4">
                    <x-toggle wire:model="auto_send"
                        label="Auto-send to the client"
                        help="Email each scheduled report to the client's contact email automatically once it generates, with the PDF attached. Leave off to review and send by hand." />
                </div>

                @php($nextRun = \App\Enums\ReportFrequency::tryFrom($report_frequency)?->nextGenerationDate())
                <p class="mt-3 text-xs text-faint">
                    The next report will be generated automatically
                    @if ($nextRun) on <span class="text-muted">{{ $nextRun->format('j M Y') }}</span>, @endif
                    and again once each period closes. See every scheduled site under
                    <a href="{{ route('reports.scheduled') }}" wire:navigate class="cr-link">Reports → Scheduled</a>.
                </p>
            @endif
        </div>

        <div class="flex items-center gap-3 border-t border-line pt-5">
            <x-button type="submit" variant="primary">{{ $site ? 'Save changes' : 'Create site' }}</x-button>
            <x-button :href="$site ? route('sites.show', $site) : route('sites.index')">Cancel</x-button>
        </div>
    </form>
</div>
