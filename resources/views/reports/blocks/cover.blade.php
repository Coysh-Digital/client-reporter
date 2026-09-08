@php
    use App\Support\Branding\Color;
    use App\Support\ReportLang;

    $style = $branding->reportCoverStyle;
    $minimal = $style === 'minimal';
    $bold = $style === 'bold';
    $client = $data['client'] ?? '';
    $site = $data['site'] ?? '';
    $period = $data['period'] ?? '';
    $showPeriod = $branding->reportCoverShowPeriod;
    $contact = $branding->reportCoverShowContact ? ($data['contact'] ?? null) : null;
    $preparedOn = $branding->reportCoverShowContact ? ($data['prepared_on'] ?? null) : null;
    $eyebrow = $branding->reportCoverLabel ?: ReportLang::get('cover.eyebrow');
    // Explicit cover commentary always shows; the agency tagline is only a
    // fallback, and obeys the "show tagline" switch.
    $intro = $commentary ?: ($branding->reportCoverShowTagline ? $branding->tagline : null);

    $coverColor = $branding->coverColor();
    $coverImage = $branding->reportCoverImageUrl;
    $hasImage = (bool) $coverImage;

    // Foreground tones for the branded band. Over a background image the band
    // wears a dark scrim, so text is white; otherwise the tones adapt to the
    // cover colour (white ink on a dark cover, dark ink on a light one) with
    // muted/faint steps mixed back towards it. The divider is nudged until it
    // reads against the band, so a light secondary stays visible either way.
    if ($hasImage) {
        // A dark version of the cover colour shows through if the image can't be
        // painted (e.g. the dompdf driver), keeping the white text legible.
        $bandFallback = Color::mix($coverColor, '#000000', 0.5);
        $bandInk = '#ffffff';
        $bandMuted = 'rgba(255,255,255,0.86)';
        $bandFaint = 'rgba(255,255,255,0.72)';
        $bandHairlineStrong = 'rgba(255,255,255,0.5)';
        $bandHairline = 'rgba(255,255,255,0.32)';
        $divider = Color::readable($branding->secondaryColor, '#111111', 3.0);
    } else {
        $bandFallback = $coverColor;
        $bandInk = Color::inkOn($coverColor);
        $bandMuted = Color::mix($bandInk, $coverColor, 0.22);
        $bandFaint = Color::mix($bandInk, $coverColor, 0.42);
        $bandHairlineStrong = Color::mix($bandInk, $coverColor, 0.55);
        $bandHairline = Color::mix($bandInk, $coverColor, 0.7);
        $divider = Color::readable($branding->secondaryColor, $coverColor, 3.0);
    }
    // The scrim is baked into the background as a gradient layer over the image
    // (rather than a separate positioned element, which the dompdf driver can't
    // place), so the same declaration keeps white text legible everywhere.
    $bandBg = 'background-color:'.$bandFallback.';';
    if ($hasImage) {
        $bandBg .= "background-image:linear-gradient(rgba(17,15,20,0.45),rgba(17,15,20,0.55)),url('".$coverImage."');"
            .'background-size:cover;background-position:center;background-repeat:no-repeat;';
    }
@endphp

@if ($minimal)
    {{-- Minimal: light cover with a slim accent rule. --}}
    <div class="cover-minimal">
        <div style="height:5px;width:56px;background:{{ $coverColor }};border-radius:2px;margin-bottom:24px;"></div>
        @if ($branding->hasLogo())
            <img src="{{ $branding->logoUrl }}" alt="{{ $branding->agencyName }}" style="height:40px;max-width:240px;">
        @else
            <div style="font-family:{{ $branding->headingFontStack() }};font-size:19px;font-weight:600;color:var(--brand-primary-ink);">{{ $branding->agencyName }}</div>
        @endif
        <div class="metric-label" style="margin-top:30px;">{{ $eyebrow }}</div>
        <h1 style="font-size:40px;line-height:1.04;margin-top:8px;color:#211f1b;">{{ $client }}</h1>
        <div class="muted" style="margin-top:8px;font-size:16px;font-variant-numeric:tabular-nums;">{{ $site }}@if ($showPeriod) &middot; {{ $period }}@endif</div>
        @if ($intro)
            <p style="margin-top:22px;font-size:15px;line-height:1.6;color:#57534a;max-width:460px;">{{ $intro }}</p>
        @endif
    </div>
@else
    {{-- Standard / bold: full-bleed branded cover band, over a colour or image.
         The dark fallback colour keeps text readable if a renderer can't paint
         the image itself. --}}
    <div class="cover-band" style="{{ $bandBg }}color:{{ $bandMuted }};margin:-34px -46px 0;padding:{{ $bold ? '60px 46px 54px' : '52px 46px 46px' }};">
        <div>
            <table class="cover-split" style="width:100%;border-collapse:collapse;"><tr>
                <td style="vertical-align:middle;">
                    @if ($branding->hasLogo())
                        <img src="{{ $branding->logoUrl }}" alt="{{ $branding->agencyName }}" style="height:42px;max-width:240px;">
                    @else
                        <div style="font-family:{{ $branding->headingFontStack() }};font-size:19px;font-weight:600;color:{{ $bandInk }};">{{ $branding->agencyName }}</div>
                    @endif
                </td>
                <td style="vertical-align:middle;text-align:right;">
                    <span style="display:inline-block;padding:4px 12px;border:1px solid {{ $bandHairlineStrong }};border-radius:999px;font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;color:{{ $bandMuted }};">{{ $eyebrow }}</span>
                </td>
            </tr></table>

            <div style="margin-top:{{ $bold ? '60px' : '52px' }};">
                <div style="height:4px;width:44px;background:{{ $divider }};border-radius:2px;margin-bottom:20px;"></div>
                <h1 style="font-size:{{ $bold ? '54px' : '46px' }};line-height:1.02;font-weight:600;letter-spacing:-.02em;color:{{ $bandInk }};margin:0;">{{ $client }}</h1>
                <div style="margin-top:14px;font-size:16px;color:{{ $bandFaint }};font-variant-numeric:tabular-nums;">{{ $site }}@if ($showPeriod) &middot; {{ $period }}@endif</div>
            </div>

            @if ($intro || $contact || $preparedOn)
                <table class="cover-split" style="width:100%;border-collapse:collapse;margin-top:44px;border-top:1px solid {{ $bandHairline }};"><tr>
                    <td style="vertical-align:top;padding-top:20px;max-width:440px;font-size:14.5px;line-height:1.6;color:{{ $bandMuted }};">{{ $intro }}</td>
                    @if ($contact || $preparedOn)
                        <td style="vertical-align:top;padding-top:20px;text-align:right;font-size:12.5px;color:{{ $bandFaint }};line-height:1.7;white-space:nowrap;">
                            @if ($contact) {{ ReportLang::get('cover.prepared_for') }}<br><span style="color:{{ $bandInk }};font-weight:600;">{{ $contact }}</span><br>@endif
                            {{ $preparedOn }}
                        </td>
                    @endif
                </tr></table>
            @endif
        </div>
    </div>
@endif
