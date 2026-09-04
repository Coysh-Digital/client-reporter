<div>
    @php
        $scopeLabel = match ($scope) {
            'site' => 'Site branding · ' . $site->name,
            'client' => 'Client branding · ' . $client->name,
            default => 'Agency branding',
        };
        $subtitle = match ($scope) {
            'global' => 'The default branding applied to every client-facing report and email.',
            default => 'Overrides the agency branding for this ' . $scope . '. Leave a field blank to inherit.',
        };
    @endphp

    {{-- Load the currently selected fonts so the picker and live preview render them. --}}
    @php
        $previewFontUrl = \App\Support\GoogleFonts::googleUrl([
            \App\Support\GoogleFonts::extractFamily($heading_font),
            \App\Support\GoogleFonts::extractFamily($body_font),
        ]);
    @endphp
    @if ($previewFontUrl)
        <link href="{{ $previewFontUrl }}" rel="stylesheet">
    @endif

    <x-page-header :title="$scopeLabel" :subtitle="$subtitle" />

    @if (session('status'))
        <div class="mb-4 rounded-md bg-ok-soft px-3 py-2 text-sm text-ok">{{ session('status') }}</div>
    @endif

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-5">
        {{-- Editor --}}
        <div class="space-y-6 lg:col-span-3">
            <div class="cr-card px-6 py-5 space-y-4">
                <h2 class="text-sm font-semibold text-ink">Identity</h2>
                <div>
                    <label for="agency_name" class="cr-label">Agency name</label>
                    <input wire:model.live.debounce.400ms="agency_name" id="agency_name" type="text" class="cr-input"
                           placeholder="{{ $scope === 'global' ? config('client-reporter.name') : 'Inherit from agency' }}">
                    @error('agency_name') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="tagline" class="cr-label">Tagline</label>
                    <input wire:model.live.debounce.400ms="tagline" id="tagline" type="text" class="cr-input">
                    @error('tagline') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="cr-label">Logo</label>
                        @if ($profile->logoUrl())
                            <div class="mb-2 flex items-center gap-3">
                                <img src="{{ $profile->logoUrl() }}" alt="Logo" class="h-10 rounded border border-line bg-white p-1">
                                <button type="button" wire:click="removeLogo" class="text-xs text-danger hover:underline">Remove</button>
                            </div>
                        @endif
                        <input wire:model="logo" type="file" accept="image/*" class="block w-full text-sm text-muted file:mr-3 file:rounded-md file:border-0 file:bg-accent-soft file:px-3 file:py-1.5 file:text-accent">
                        <div wire:loading wire:target="logo" class="mt-1 text-xs text-muted">Uploading…</div>
                        @error('logo') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="cr-label">Favicon</label>
                        @if ($profile->faviconUrl())
                            <div class="mb-2 flex items-center gap-3">
                                <img src="{{ $profile->faviconUrl() }}" alt="Favicon" class="h-8 w-8 rounded border border-line bg-white p-1">
                                <button type="button" wire:click="removeFavicon" class="text-xs text-danger hover:underline">Remove</button>
                            </div>
                        @endif
                        <input wire:model="favicon" type="file" accept="image/*" class="block w-full text-sm text-muted file:mr-3 file:rounded-md file:border-0 file:bg-accent-soft file:px-3 file:py-1.5 file:text-accent">
                        @error('favicon') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="cr-card px-6 py-5 space-y-4">
                <h2 class="text-sm font-semibold text-ink">Colours &amp; typography</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="primary_color" class="cr-label">Primary colour</label>
                        <div class="flex items-center gap-2">
                            <input wire:model.live="primary_color" id="primary_color" type="color" class="h-9 w-12 rounded border border-line-strong">
                            <input wire:model.live.debounce.400ms="primary_color" type="text" class="cr-input" placeholder="#33406b">
                        </div>
                        @error('primary_color') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="secondary_color" class="cr-label">Secondary colour</label>
                        <div class="flex items-center gap-2">
                            <input wire:model.live="secondary_color" id="secondary_color" type="color" class="h-9 w-12 rounded border border-line-strong">
                            <input wire:model.live.debounce.400ms="secondary_color" type="text" class="cr-input" placeholder="#8a6a2c">
                        </div>
                        @error('secondary_color') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-font-picker model="heading_font" label="Heading font" :current="$heading_font" wire:key="heading-font-picker" />
                    <x-font-picker model="body_font" label="Body font" :current="$body_font" wire:key="body-font-picker" />
                </div>
                <p class="mt-2 text-xs text-faint">Choose any Google Font. The report loads it automatically for your clients.</p>
                <div>
                    <label for="report_cover_style" class="cr-label">Report cover style</label>
                    <select wire:model.live="report_cover_style" id="report_cover_style" class="cr-input max-w-xs">
                        <option value="minimal">Minimal</option>
                        <option value="standard">Standard</option>
                        <option value="bold">Bold</option>
                    </select>
                </div>
            </div>

            <div class="cr-card px-6 py-5 space-y-4">
                <h2 class="text-sm font-semibold text-ink">Contact &amp; footers</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="website" class="cr-label">Website</label><input wire:model="website" id="website" type="url" class="cr-input" placeholder="https://">@error('website')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror</div>
                    <div><label for="email" class="cr-label">Email</label><input wire:model="email" id="email" type="email" class="cr-input">@error('email')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror</div>
                    <div><label for="phone" class="cr-label">Phone</label><input wire:model="phone" id="phone" type="text" class="cr-input"></div>
                    <div><label for="address" class="cr-label">Address</label><input wire:model="address" id="address" type="text" class="cr-input"></div>
                </div>
                <div>
                    <label for="report_footer" class="cr-label">Report footer</label>
                    <textarea wire:model="report_footer" id="report_footer" rows="2" class="cr-input"></textarea>
                </div>
                <div>
                    <label for="email_footer" class="cr-label">Email footer</label>
                    <textarea wire:model="email_footer" id="email_footer" rows="2" class="cr-input"></textarea>
                </div>
            </div>

            <div class="cr-card px-6 py-5 space-y-3">
                <h2 class="text-sm font-semibold text-ink">Custom CSS <span class="font-normal text-faint">(advanced)</span></h2>
                <p class="text-xs text-muted">Applied only to client-facing report rendering. Use to fine-tune typography and spacing.</p>
                <textarea wire:model="custom_css" rows="4" class="cr-input font-mono text-xs" placeholder=".report-cover h1 { letter-spacing: -0.02em; }"></textarea>
                @error('custom_css') <p class="text-xs text-danger">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="cr-btn cr-btn-primary">Save branding</button>
                <span wire:loading wire:target="save" class="text-sm text-muted">Saving…</span>
            </div>
        </div>

        {{-- Live preview --}}
        <div class="lg:col-span-2">
            <div class="sticky top-6">
                <p class="cr-eyebrow mb-2">Report cover preview</p>
                @php
                    $primary = preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $primary_color) ? $primary_color : '#33406b';
                    $secondary = preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $secondary_color) ? $secondary_color : '#8a6a2c';
                    $displayName = $agency_name ?: ($scope === 'global' ? config('client-reporter.name') : ($client->name ?? config('client-reporter.name')));
                    $logoPreview = $logo ? $logo->temporaryUrl() : $profile->logoUrl();
                    $headingFontPreview = $heading_font ?: "'Source Serif 4', Georgia, serif";
                    $isMinimal = $report_cover_style === 'minimal';
                    $isBold = $report_cover_style === 'bold';
                @endphp
                <div class="overflow-hidden rounded-xl border border-line bg-white shadow-sm">
                    @if ($isMinimal)
                        <div class="px-7 py-9">
                            <div style="height:5px;width:52px;border-radius:2px;background:{{ $primary }};margin-bottom:20px;"></div>
                            @if ($logoPreview)
                                <img src="{{ $logoPreview }}" alt="Logo" class="mb-6 h-9 object-contain">
                            @else
                                <div class="mb-6 text-lg font-semibold" style="color: {{ $primary }};font-family: {{ $headingFontPreview }};">{{ $displayName }}</div>
                            @endif
                            <div class="text-[11px] font-semibold uppercase tracking-[0.09em]" style="color: {{ $secondary }};">Website report</div>
                            <h3 class="mt-2 text-2xl font-semibold text-ink" style="font-family: {{ $headingFontPreview }};">{{ $client->name ?? 'Client name' }}</h3>
                            <p class="mt-1 text-sm text-faint tnum">clientsite.com · 1–31 August 2026</p>
                            @if ($tagline)<p class="mt-5 text-sm text-muted">{{ $tagline }}</p>@endif
                        </div>
                    @else
                        <div class="px-7 {{ $isBold ? 'py-12' : 'py-10' }}" style="background: {{ $primary }};">
                            <div class="flex items-center justify-between">
                                @if ($logoPreview)
                                    <img src="{{ $logoPreview }}" alt="Logo" class="h-9 object-contain">
                                @else
                                    <div class="font-semibold text-white" style="font-family: {{ $headingFontPreview }};">{{ $displayName }}</div>
                                @endif
                                <span class="text-[10px] uppercase tracking-[0.14em]" style="color: rgba(255,255,255,.7);">Website report</span>
                            </div>
                            <h3 class="mt-10 font-semibold text-white" style="font-family: {{ $headingFontPreview }};font-size: {{ $isBold ? '34px' : '30px' }};line-height:1.04;">{{ $client->name ?? 'Client name' }}</h3>
                            <p class="mt-2.5 text-sm tnum" style="color: rgba(255,255,255,.72);">clientsite.com · 1–31 August 2026</p>
                            @if ($tagline)
                                <p class="mt-6 border-t pt-4 text-[13px]" style="border-color: rgba(255,255,255,.16); color: rgba(255,255,255,.82);">{{ $tagline }}</p>
                            @endif
                        </div>
                    @endif
                    @if ($report_footer)
                        <div class="border-t border-line px-7 py-3 text-center text-xs text-faint">{{ $report_footer }}</div>
                    @endif
                </div>
                <p class="mt-3 text-xs text-muted">This is how the cover of a client-facing report will look. Client Reporter's own branding never appears here.</p>
            </div>
        </div>
    </form>
</div>
