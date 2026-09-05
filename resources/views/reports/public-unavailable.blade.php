<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ \App\Support\ReportLang::get('public.unavailable.title') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #faf9f6; color: #1b1a18; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-align: center; }
        .box { max-width: 360px; padding: 32px; }
        h1 { font-size: 19px; margin: 0 0 8px; }
        p { color: #6c675f; font-size: 14px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>{{ \App\Support\ReportLang::get('public.unavailable.heading') }}</h1>
        <p>{{ \App\Support\ReportLang::get('public.unavailable.body') }}</p>
    </div>
</body>
</html>
