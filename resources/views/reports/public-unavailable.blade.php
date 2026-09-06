{{--
    Shown for an expired, revoked or unknown share link. Branded for the agency
    the link belonged to when that is known, otherwise the agency's default
    branding; inline styles only, for the report CSP.
--}}
@php use App\Support\ReportLang; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ ReportLang::get('public.unavailable.title') }} · {{ $branding->agencyName }}</title>
    @if ($branding->faviconUrl)
        <link rel="icon" href="{{ $branding->faviconUrl }}">
    @endif
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #faf9f6; color: #1b1a18; font-family: {!! $branding->bodyFontStack() !!}; text-align: center; }
        .box { max-width: 380px; padding: 32px; }
        .brand { margin-bottom: 20px; }
        .brand img { height: 36px; }
        .brand span { font-family: {!! $branding->headingFontStack() !!}; font-size: 20px; font-weight: 600; color: {{ $branding->primaryColor }}; }
        h1 { font-family: {!! $branding->headingFontStack() !!}; font-size: 19px; margin: 0 0 8px; }
        p { color: #6c675f; font-size: 14px; }
        a { color: {{ $branding->primaryColor }}; }
    </style>
</head>
<body>
    <div class="box">
        <div class="brand">
            @if ($branding->hasLogo())
                <img src="{{ $branding->logoUrl }}" alt="{{ $branding->agencyName }}">
            @else
                <span>{{ $branding->agencyName }}</span>
            @endif
        </div>
        <h1>{{ ReportLang::get('public.unavailable.heading') }}</h1>
        <p>{{ ReportLang::get('public.unavailable.body') }}</p>
        @if ($branding->email)
            <p><a href="mailto:{{ $branding->email }}">{{ $branding->email }}</a></p>
        @endif
    </div>
</body>
</html>
