@php
    $style = $branding->reportCoverStyle;
    $minimal = $style === 'minimal';
    $bold = $style === 'bold';
    $client = $data['client'] ?? '';
    $site = $data['site'] ?? '';
    $period = $data['period'] ?? '';
    $contact = $data['contact'] ?? null;
    $preparedOn = $data['prepared_on'] ?? null;
    $intro = $commentary ?: $branding->tagline;
@endphp

@if ($minimal)
    {{-- Minimal: light cover with a slim accent rule. --}}
    <div class="cover-minimal">
        <div style="height:5px;width:56px;background:var(--brand-primary);border-radius:2px;margin-bottom:24px;"></div>
        @if ($branding->hasLogo())
            <img src="{{ $branding->logoUrl }}" alt="{{ $branding->agencyName }}" style="height:40px;max-width:240px;">
        @else
            <div style="font-family:{!! $branding->headingFont !!};font-size:19px;font-weight:600;color:var(--brand-primary);">{{ $branding->agencyName }}</div>
        @endif
        <div class="metric-label" style="margin-top:30px;">Website report</div>
        <h1 style="font-size:40px;line-height:1.04;margin-top:8px;color:#211f1b;">{{ $client }}</h1>
        <div class="muted" style="margin-top:8px;font-size:16px;font-variant-numeric:tabular-nums;">{{ $site }} &middot; {{ $period }}</div>
        @if ($intro)
            <p style="margin-top:22px;font-size:15px;line-height:1.6;color:#57534a;max-width:460px;">{{ $intro }}</p>
        @endif
    </div>
@else
    {{-- Standard / bold: full-bleed branded cover band. --}}
    <div class="cover-band" style="background:var(--brand-primary);color:#eef0f6;margin:-32px -44px 0;padding:{{ $bold ? '60px 44px 54px' : '52px 44px 46px' }};">
        <table style="width:100%;border-collapse:collapse;"><tr>
            <td style="vertical-align:middle;">
                @if ($branding->hasLogo())
                    <img src="{{ $branding->logoUrl }}" alt="{{ $branding->agencyName }}" style="height:42px;max-width:240px;">
                @else
                    <div style="font-family:{!! $branding->headingFont !!};font-size:19px;font-weight:600;color:#fff;">{{ $branding->agencyName }}</div>
                @endif
            </td>
            <td style="vertical-align:middle;text-align:right;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#a9b0cd;">Website report</td>
        </tr></table>

        <div style="margin-top:{{ $bold ? '60px' : '52px' }};">
            <h1 style="font-size:{{ $bold ? '54px' : '46px' }};line-height:1.02;font-weight:600;letter-spacing:-.02em;color:#fff;margin:0;">{{ $client }}</h1>
            <div style="margin-top:14px;font-size:16px;color:#c7ccdf;font-variant-numeric:tabular-nums;">{{ $site }} &middot; {{ $period }}</div>
        </div>

        @if ($intro || $contact || $preparedOn)
            <table style="width:100%;border-collapse:collapse;margin-top:44px;border-top:1px solid rgba(255,255,255,.16);"><tr>
                <td style="vertical-align:top;padding-top:20px;max-width:440px;font-size:14.5px;line-height:1.6;color:#d7dbe8;">{{ $intro }}</td>
                @if ($contact || $preparedOn)
                    <td style="vertical-align:top;padding-top:20px;text-align:right;font-size:12.5px;color:#a9b0cd;line-height:1.7;white-space:nowrap;">
                        @if ($contact) Prepared for<br><span style="color:#fff;font-weight:600;">{{ $contact }}</span><br>@endif
                        {{ $preparedOn }}
                    </td>
                @endif
            </tr></table>
        @endif
    </div>
@endif
