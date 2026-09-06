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


    <form wire:submit="save" class="grid gap-6 lg:grid-cols-5">
        {{-- Editor --}}
        <div class="space-y-6 lg:col-span-3">
            <div class="cr-card px-6 py-5 space-y-4">
                <h2 class="text-sm font-semibold text-ink">Identity</h2>
                <x-field label="Agency name" for="agency_name">
                    <input wire:model.live.debounce.400ms="agency_name" id="agency_name" type="text" class="cr-input"
                           placeholder="{{ $scope === 'global' ? config('client-reporter.name') : 'Inherit from agency' }}">
                </x-field>
                <x-field label="Tagline" for="tagline" optional>
                    <input wire:model.live.debounce.400ms="tagline" id="tagline" type="text" class="cr-input">
                </x-field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Logo" for="logo" help="PNG, JPG or WebP up to 2 MB.">
                        @if ($profile->logoUrl())
                            <div class="mb-2 flex items-center gap-3">
                                <img src="{{ $profile->logoUrl() }}" alt="Current logo" class="h-10 rounded border border-line bg-white p-1">
                                <x-button size="sm" variant="ghost" wire:click="removeLogo">Remove logo</x-button>
                            </div>
                        @endif
                        <input wire:model="logo" id="logo" type="file" accept="image/*" class="block w-full text-sm text-muted file:mr-3 file:rounded-md file:border-0 file:bg-accent-soft file:px-3 file:py-1.5 file:text-accent">
                        <div wire:loading wire:target="logo" class="mt-1 text-xs text-muted">Uploading…</div>
                    </x-field>
                    <x-field label="Favicon" for="favicon" help="Square image up to 512 KB.">
                        @if ($profile->faviconUrl())
                            <div class="mb-2 flex items-center gap-3">
                                <img src="{{ $profile->faviconUrl() }}" alt="Current favicon" class="h-8 w-8 rounded border border-line bg-white p-1">
                                <x-button size="sm" variant="ghost" wire:click="removeFavicon">Remove favicon</x-button>
                            </div>
                        @endif
                        <input wire:model="favicon" id="favicon" type="file" accept="image/*" class="block w-full text-sm text-muted file:mr-3 file:rounded-md file:border-0 file:bg-accent-soft file:px-3 file:py-1.5 file:text-accent">
                    </x-field>
                </div>
            </div>

            <div class="cr-card px-6 py-5 space-y-4">
                <h2 class="text-sm font-semibold text-ink">Colours &amp; typography</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Primary colour" for="primary_color">
                        <div class="flex items-center gap-2">
                            <input wire:model.live="primary_color" id="primary_color-swatch" type="color" aria-label="Primary colour picker" class="h-9 w-12 rounded border border-line-strong">
                            <input wire:model.live.debounce.400ms="primary_color" id="primary_color" type="text" class="cr-input" placeholder="#33406b">
                        </div>
                    </x-field>
                    <x-field label="Secondary colour" for="secondary_color">
                        <div class="flex items-center gap-2">
                            <input wire:model.live="secondary_color" id="secondary_color-swatch" type="color" aria-label="Secondary colour picker" class="h-9 w-12 rounded border border-line-strong">
                            <input wire:model.live.debounce.400ms="secondary_color" id="secondary_color" type="text" class="cr-input" placeholder="#8a6a2c">
                        </div>
                    </x-field>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-font-picker model="heading_font" label="Heading font" :current="$heading_font" wire:key="heading-font-picker" />
                    <x-font-picker model="body_font" label="Body font" :current="$body_font" wire:key="body-font-picker" />
                </div>
                <p class="mt-2 text-xs text-faint">Choose any Google Font. The report loads it automatically for your clients.</p>
                <x-field label="Report cover style" for="report_cover_style">
                    <select wire:model.live="report_cover_style" id="report_cover_style" class="cr-input max-w-xs">
                        <option value="minimal">Minimal</option>
                        <option value="standard">Standard</option>
                        <option value="bold">Bold</option>
                    </select>
                </x-field>
            </div>

            <div class="cr-card px-6 py-5 space-y-4">
                <h2 class="text-sm font-semibold text-ink">Contact &amp; footers</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Website" for="website"><input wire:model="website" id="website" type="url" class="cr-input" placeholder="https://"></x-field>
                    <x-field label="Email" for="email"><input wire:model="email" id="email" type="email" class="cr-input"></x-field>
                    <x-field label="Phone" for="phone"><input wire:model="phone" id="phone" type="tel" class="cr-input"></x-field>
                    <x-field label="Address" for="address"><input wire:model="address" id="address" type="text" class="cr-input"></x-field>
                </div>
                <x-field label="Report footer" for="report_footer" help="Printed at the foot of every report page.">
                    <textarea wire:model="report_footer" id="report_footer" rows="2" class="cr-input"></textarea>
                </x-field>
                <x-field label="Email footer" for="email_footer" help="Added below report emails sent to clients.">
                    <textarea wire:model="email_footer" id="email_footer" rows="2" class="cr-input"></textarea>
                </x-field>
            </div>

            <div class="cr-card px-6 py-5 space-y-3">
                <h2 class="text-sm font-semibold text-ink">Custom CSS <span class="font-normal text-faint">(advanced)</span></h2>
                <x-field label="Report stylesheet" for="custom_css" help="Applied only to client-facing report rendering. Use it to fine-tune typography and spacing; imports, URLs and scripts are not allowed.">
                    <textarea wire:model="custom_css" id="custom_css" rows="4" class="cr-input font-mono text-xs" placeholder=".report-cover h1 { letter-spacing: -0.02em; }"></textarea>
                </x-field>
            </div>

            <div class="sticky bottom-0 flex items-center gap-3 border-t border-line py-3" style="background:color-mix(in srgb, var(--color-paper) 92%, transparent);backdrop-filter:blur(6px);">
                <x-button type="submit" variant="primary">Save branding</x-button>
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
                            <div class="text-2xs font-semibold uppercase tracking-[0.09em]" style="color: {{ $secondary }};">Website report</div>
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
                                <span class="text-2xs uppercase tracking-[0.14em]" style="color: rgba(255,255,255,.7);">Website report</span>
                            </div>
                            <h3 class="mt-10 font-semibold text-white" style="font-family: {{ $headingFontPreview }};font-size: {{ $isBold ? '34px' : '30px' }};line-height:1.04;">{{ $client->name ?? 'Client name' }}</h3>
                            <p class="mt-2.5 text-sm tnum" style="color: rgba(255,255,255,.72);">clientsite.com · 1–31 August 2026</p>
                            @if ($tagline)
                                <p class="mt-6 border-t pt-4 text-sm" style="border-color: rgba(255,255,255,.16); color: rgba(255,255,255,.82);">{{ $tagline }}</p>
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
