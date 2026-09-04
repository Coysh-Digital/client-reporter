<div>
    <x-page-header :title="$site ? 'Edit site' : 'New site'"
                   :subtitle="$site?->name ?? 'Add a website to a client.'" />

    <form wire:submit="save" class="cr-card max-w-xl px-6 py-6 space-y-5">
        <div>
            <label for="client_id" class="cr-label">Client</label>
            <select wire:model="client_id" id="client_id" class="cr-input" @disabled($site)>
                <option value="">Select a client…</option>
                @foreach ($this->clients() as $clientOption)
                    <option value="{{ $clientOption->id }}">{{ $clientOption->name }}</option>
                @endforeach
            </select>
            @error('client_id') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="url" class="cr-label">Website URL</label>
            <input wire:model.blur="url" id="url" type="url" placeholder="https://example.com" class="cr-input" required>
            @error('url') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="name" class="cr-label">Site name</label>
            <input wire:model="name" id="name" type="text" class="cr-input" required>
            <p class="mt-1 text-xs text-faint">Suggested from the URL — edit to taste.</p>
            @error('name') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="cms_type" class="cr-label">CMS</label>
                <select wire:model="cms_type" id="cms_type" class="cr-input">
                    <option value="">Unknown</option>
                    <option value="wordpress">WordPress</option>
                    <option value="craft">Craft CMS</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label for="environment" class="cr-label">Environment</label>
                <select wire:model="environment" id="environment" class="cr-input">
                    <option value="production">Production</option>
                    <option value="staging">Staging</option>
                    <option value="development">Development</option>
                </select>
            </div>
            <div>
                <label for="is_active" class="cr-label">Status</label>
                <label class="flex h-[38px] items-center gap-2 text-sm text-muted">
                    <input wire:model="is_active" type="checkbox" class="rounded border-line-strong text-accent focus:ring-accent">
                    Active
                </label>
            </div>
        </div>

        <div>
            <label for="timezone" class="cr-label">Reporting timezone</label>
            <select wire:model="timezone" id="timezone" class="cr-input">
                @foreach ($this->timezones() as $tz)
                    <option value="{{ $tz }}">{{ $tz }}</option>
                @endforeach
            </select>
            @error('timezone') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div class="border-t border-line pt-5">
            <div class="cr-eyebrow" style="color:var(--color-secondary);">Reporting schedule</div>
            <p class="mt-1 text-[12.5px] text-faint">Optional. When set, a report is generated automatically once each period closes, ready for you to review and send. Leave off for sites you report on manually.</p>

            <div class="mt-3 grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="report_frequency" class="cr-label">Frequency</label>
                    <select wire:model.live="report_frequency" id="report_frequency" class="cr-input">
                        @foreach ($this->frequencies() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('report_frequency') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                @if ($report_frequency !== 'none')
                    <div>
                        <label for="report_template_id" class="cr-label">Report template <span class="text-faint">(optional)</span></label>
                        <select wire:model="report_template_id" id="report_template_id" class="cr-input">
                            <option value="">Default sections</option>
                            @foreach ($this->templates() as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                        @error('report_template_id') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-3 border-t border-line pt-5">
            <button type="submit" class="cr-btn cr-btn-primary">{{ $site ? 'Save changes' : 'Create site' }}</button>
            <a href="{{ $site ? route('sites.show', $site) : route('sites.index') }}" wire:navigate class="cr-btn cr-btn-secondary">Cancel</a>
        </div>
    </form>
</div>
