<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ \App\Support\ReportLang::get('public.password.title') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #faf9f6; color: #1b1a18; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .card { background: #fff; border: 1px solid #e8e3da; border-radius: 8px; padding: 32px; width: 340px; }
        h1 { font-size: 18px; margin: 0 0 6px; }
        p { color: #6c675f; font-size: 14px; margin: 0 0 18px; }
        input { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid #d8d2c6; border-radius: 6px; font-size: 14px; }
        button { width: 100%; margin-top: 14px; padding: 9px; border: 0; border-radius: 6px; background: #3a3d6b; color: #fff; font-size: 14px; cursor: pointer; }
        .err { color: #a13b32; font-size: 13px; margin-top: 10px; }
    </style>
</head>
<body>
    <form class="card" method="POST" action="{{ route('public-report.unlock', ['token' => $token]) }}">
        @csrf
        <h1>{{ \App\Support\ReportLang::get('public.password.heading') }}</h1>
        <p>{{ \App\Support\ReportLang::get('public.password.body') }}</p>
        <input type="password" name="password" placeholder="{{ \App\Support\ReportLang::get('public.password.placeholder') }}" autofocus required>
        <button type="submit">{{ \App\Support\ReportLang::get('public.password.button') }}</button>
        @if ($failed)
            <p class="err">{{ \App\Support\ReportLang::get('public.password.error') }}</p>
        @endif
    </form>
</body>
</html>
