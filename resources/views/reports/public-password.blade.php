{{--
    The password gate in front of a protected share link. Carries the agency's
    branding (never the product's) and, like the report itself, uses only
    inline styles so it renders with the strict report CSP.
--}}
@php
    use App\Support\ReportLang;
    $fontUrl = \App\Support\GoogleFonts::googleUrl($branding->fontFamilies());
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ ReportLang::get('public.password.title') }} · {{ $branding->agencyName }}</title>
    @if ($branding->faviconUrl)
        <link rel="icon" href="{{ $branding->faviconUrl }}">
    @endif
    @if ($fontUrl)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="{{ $fontUrl }}" rel="stylesheet">
    @endif
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #faf9f6; color: #1b1a18; font-family: {!! $branding->bodyFontStack() !!}; }
        .wrap { width: 360px; max-width: calc(100vw - 32px); }
        .brand { text-align: center; margin-bottom: 20px; }
        .brand img { height: 36px; }
        .brand span { font-family: {!! $branding->headingFontStack() !!}; font-size: 20px; font-weight: 600; color: {{ $branding->primaryColor }}; }
        .card { background: #fff; border: 1px solid #e8e3da; border-top: 3px solid {{ $branding->primaryColor }}; border-radius: 8px; padding: 32px; }
        h1 { font-family: {!! $branding->headingFontStack() !!}; font-size: 18px; margin: 0 0 6px; }
        p { color: #6c675f; font-size: 14px; margin: 0 0 18px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        input { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid #d8d2c6; border-radius: 6px; font-size: 14px; font-family: inherit; }
        input:focus { outline: 2px solid {{ $branding->primaryColor }}; outline-offset: 1px; }
        button { width: 100%; margin-top: 14px; padding: 10px; border: 0; border-radius: 6px; background: {{ $branding->primaryColor }}; color: #fff; font-size: 14px; font-family: inherit; cursor: pointer; }
        .err { color: #a13b32; font-size: 13px; margin-top: 10px; }
        .foot { text-align: center; color: #98938a; font-size: 12px; margin-top: 18px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">
            @if ($branding->hasLogo())
                <img src="{{ $branding->logoUrl }}" alt="{{ $branding->agencyName }}">
            @else
                <span>{{ $branding->agencyName }}</span>
            @endif
        </div>
        <form class="card" method="POST" action="{{ route('public-report.unlock', ['token' => $token]) }}">
            @csrf
            <h1>{{ ReportLang::get('public.password.heading') }}</h1>
            <p>{{ ReportLang::get('public.password.body') }}</p>
            <label for="password">{{ ReportLang::get('public.password.placeholder') }}</label>
            <input type="password" id="password" name="password" autocomplete="off" autofocus required @if ($failed) aria-invalid="true" aria-describedby="password-error" @endif>
            <button type="submit">{{ ReportLang::get('public.password.button') }}</button>
            @if ($failed)
                <p class="err" id="password-error" role="alert">{{ ReportLang::get('public.password.error') }}</p>
            @endif
        </form>
        @if ($branding->email)
            <p class="foot">{{ $branding->agencyName }} · <a href="mailto:{{ $branding->email }}" style="color:inherit;">{{ $branding->email }}</a></p>
        @endif
    </div>
</body>
</html>
