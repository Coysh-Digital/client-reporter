<div x-data="crUnsavedGuard()" x-on:input="touch()" x-on:change="touch()" x-on:saved.window="clean()">
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

    @php $inherited = $scope === 'global' ? null : app(\App\Support\Branding\BrandingResolver::class)->global(); @endphp
    <x-page-header :title="$scopeLabel" :subtitle="$subtitle" />

    @if ($inherited)
        <x-alert variant="info" class="mb-6">
            Blank fields inherit the agency branding{{ $inherited->agency_name ? ' for '.$inherited->agency_name : '' }}. Fill one in to override it for this {{ $scope }} only.
            <x-slot:action><a href="{{ route('branding.edit') }}" wire:navigate class="font-medium underline">Edit agency branding</a></x-slot:action>
        </x-alert>
    @endif


    <form wire:submit="save" class="grid gap-6 lg:grid-cols-5">
        {{-- Editor --}}
        <div class="space-y-6 lg:col-span-3">
            <div class="cr-card px-6 py-5 space-y-4">
                <h2 class="text-sm font-semibold text-ink">Identity</h2>
                <x-field label="Agency name" for="agency_name" :help="$inherited && $agency_name === '' ? 'Inherits “'.($inherited->agency_name ?: config('client-reporter.name')).'” from the agency branding.' : null">
                    <input wire:model.live.debounce.400ms="agency_name" id="agency_name" type="text" class="cr-input"
                           placeholder="{{ $inherited ? ($inherited->agency_name ?: config('client-reporter.name')) : config('client-reporter.name') }}">
                </x-field>
                <x-field label="Tagline" for="tagline" optional :help="$inherited && $tagline === '' && $inherited->tagline ? 'Inherits “'.$inherited->tagline.'”.' : null">
                    <input wire:model.live.debounce.400ms="tagline" id="tagline" type="text" class="cr-input" placeholder="{{ $inherited?->tagline ?? '' }}">
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
                            <input wire:model.live.debounce.400ms="primary_color" id="primary_color" type="text" class="cr-input" placeholder="{{ $inherited?->primary_color ?: '#33406b' }}">
                        </div>
                    </x-field>
                    <x-field label="Secondary colour" for="secondary_color">
                        <div class="flex items-center gap-2">
                            <input wire:model.live="secondary_color" id="secondary_color-swatch" type="color" aria-label="Secondary colour picker" class="h-9 w-12 rounded border border-line-strong">
                            <input wire:model.live.debounce.400ms="secondary_color" id="secondary_color" type="text" class="cr-input" placeholder="{{ $inherited?->secondary_color ?: '#8a6a2c' }}">
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

                <x-field label="Banner label" for="report_cover_label" help="The small label above the client name on the cover. Leave blank for “Website report”.">
                    <input wire:model.live.debounce.400ms="report_cover_label" id="report_cover_label" type="text" maxlength="120" class="cr-input max-w-xs"
                           placeholder="{{ $inherited?->report_cover_label ?: 'Website report' }}">
                </x-field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Banner colour" for="report_cover_color" help="The cover band’s background. Leave blank to use the primary colour.">
                        <div class="flex items-center gap-2">
                            <input wire:model.live="report_cover_color" id="report_cover_color-swatch" type="color" aria-label="Banner colour picker" class="h-9 w-12 rounded border border-line-strong">
                            <input wire:model.live.debounce.400ms="report_cover_color" id="report_cover_color" type="text" class="cr-input" placeholder="{{ $inherited?->report_cover_color ?: 'Primary colour' }}">
                        </div>
                    </x-field>
                    <x-field label="Banner image" for="report_cover_image" help="Optional photo/graphic behind the banner, kept legible with an overlay. Up to 4 MB.">
                        @if ($profile->coverImageUrl())
                            <div class="mb-2 flex items-center gap-3">
                                <img src="{{ $profile->coverImageUrl() }}" alt="Current banner image" class="h-10 w-16 rounded border border-line object-cover">
                                <x-button size="sm" variant="ghost" wire:click="removeCoverImage">Remove</x-button>
                            </div>
                        @endif
                        <input wire:model="report_cover_image" id="report_cover_image" type="file" accept="image/*" class="block w-full text-sm text-muted file:mr-3 file:rounded-md file:border-0 file:bg-accent-soft file:px-3 file:py-1.5 file:text-accent">
                        <div wire:loading wire:target="report_cover_image" class="mt-1 text-xs text-muted">Uploading…</div>
                    </x-field>
                </div>

                <div>
                    <span class="cr-label">Show on the cover</span>
                    <div class="mt-1.5 space-y-1.5">
                        <x-checkbox wire:model.live="report_cover_show_tagline" id="report_cover_show_tagline" label="Tagline" />
                        <x-checkbox wire:model.live="report_cover_show_period" id="report_cover_show_period" label="Reporting period (dates)" />
                        <x-checkbox wire:model.live="report_cover_show_contact" id="report_cover_show_contact" label="“Prepared for” contact" />
                    </div>
                </div>
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
                    $primary = preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $primary_color) ? $primary_color : ($inherited?->primary_color ?: '#33406b');
                    $secondary = preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $secondary_color) ? $secondary_color : ($inherited?->secondary_color ?: '#8a6a2c');
                    $displayName = $agency_name ?: ($inherited?->agency_name ?: config('client-reporter.name'));
                    $logoPreview = $logo ? $logo->temporaryUrl() : $profile->logoUrl();
                    $headingFontPreview = $heading_font ?: "'Source Serif 4', Georgia, serif";
                    $isMinimal = $report_cover_style === 'minimal';
                    $isBold = $report_cover_style === 'bold';
                    // Match the report's legibility handling so the preview is honest:
                    // brand colours are nudged only as far as contrast requires.
                    $primaryInk = \App\Support\Branding\Color::readable($primary, '#ffffff', 4.5);
                    $secondaryInk = \App\Support\Branding\Color::readable($secondary, '#ffffff', 4.5);

                    $coverColorPreview = preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $report_cover_color) ? $report_cover_color : ($inherited?->report_cover_color ?: $primary);
                    $coverImagePreview = $report_cover_image ? $report_cover_image->temporaryUrl() : $profile->coverImageUrl();
                    $coverLabelPreview = $report_cover_label ?: ($inherited?->report_cover_label ?: 'Website report');
                    $hasImagePreview = (bool) $coverImagePreview;
                    if ($hasImagePreview) {
                        $bandInk = '#ffffff';
                        $bandMuted = 'rgba(255,255,255,0.86)';
                        $bandFaint = 'rgba(255,255,255,0.72)';
                        $bandHairline = 'rgba(255,255,255,0.32)';
                    } else {
                        $bandInk = \App\Support\Branding\Color::inkOn($coverColorPreview);
                        $bandMuted = \App\Support\Branding\Color::mix($bandInk, $coverColorPreview, 0.22);
                        $bandFaint = \App\Support\Branding\Color::mix($bandInk, $coverColorPreview, 0.42);
                        $bandHairline = \App\Support\Branding\Color::mix($bandInk, $coverColorPreview, 0.7);
                    }
                @endphp
                <div class="overflow-hidden rounded-xl border border-line bg-white shadow-sm">
                    @if ($isMinimal)
                        <div class="px-7 py-9">
                            <div style="height:5px;width:52px;border-radius:2px;background:{{ $coverColorPreview }};margin-bottom:20px;"></div>
                            @if ($logoPreview)
                                <img src="{{ $logoPreview }}" alt="Logo" class="mb-6 h-9 object-contain">
                            @else
                                <div class="mb-6 text-lg font-semibold" style="color: {{ $primaryInk }};font-family: {{ $headingFontPreview }};">{{ $displayName }}</div>
                            @endif
                            <div class="text-2xs font-semibold uppercase tracking-[0.09em]" style="color: {{ $secondaryInk }};">{{ $coverLabelPreview }}</div>
                            <h3 class="mt-2 text-2xl font-semibold text-ink" style="font-family: {{ $headingFontPreview }};">{{ $client->name ?? 'Client name' }}</h3>
                            <p class="mt-1 text-sm text-faint tnum">clientsite.com@if ($report_cover_show_period) · 1–31 August 2026@endif</p>
                            @if ($tagline && $report_cover_show_tagline)<p class="mt-5 text-sm text-muted">{{ $tagline }}</p>@endif
                        </div>
                    @else
                        <div class="px-7 {{ $isBold ? 'py-12' : 'py-10' }}" style="background-color: {{ $coverColorPreview }};@if ($hasImagePreview)background-image:linear-gradient(rgba(17,15,20,0.45),rgba(17,15,20,0.55)),url('{{ $coverImagePreview }}');background-size:cover;background-position:center;background-repeat:no-repeat;@endif">
                            <div>
                                <div class="flex items-center justify-between">
                                    @if ($logoPreview)
                                        <img src="{{ $logoPreview }}" alt="Logo" class="h-9 object-contain">
                                    @else
                                        <div class="font-semibold" style="font-family: {{ $headingFontPreview }};color: {{ $bandInk }};">{{ $displayName }}</div>
                                    @endif
                                    <span class="text-2xs uppercase tracking-[0.14em]" style="color: {{ $bandMuted }};">{{ $coverLabelPreview }}</span>
                                </div>
                                <h3 class="mt-10 font-semibold" style="font-family: {{ $headingFontPreview }};font-size: {{ $isBold ? '34px' : '30px' }};line-height:1.04;color: {{ $bandInk }};">{{ $client->name ?? 'Client name' }}</h3>
                                <p class="mt-2.5 text-sm tnum" style="color: {{ $bandFaint }};">clientsite.com@if ($report_cover_show_period) · 1–31 August 2026@endif</p>
                                @if ($tagline && $report_cover_show_tagline)
                                    <p class="mt-6 border-t pt-4 text-sm" style="border-color: {{ $bandHairline }}; color: {{ $bandMuted }};">{{ $tagline }}</p>
                                @endif
                            </div>
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
