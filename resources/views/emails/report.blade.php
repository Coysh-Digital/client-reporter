<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report->title }}</title>
</head>
<body style="margin:0; background:#f4f2ee; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#1b1a18;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f2ee; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px; background:#ffffff; border:1px solid #e6e1d8; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="height:6px; background:{{ $branding->primaryColor }};"></td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px;">
                            @if ($branding->hasLogo())
                                <img src="{{ $branding->logoUrl }}" alt="{{ $branding->agencyName }}" style="height:36px; margin-bottom:20px;">
                            @else
                                <div style="font-size:18px; font-weight:600; color:{{ $branding->primaryColor }}; margin-bottom:20px;">{{ $branding->agencyName }}</div>
                            @endif

                            <h1 style="font-size:20px; margin:0 0 6px;">{{ $report->title }}</h1>
                            <p style="color:#6c675f; font-size:14px; margin:0 0 20px;">{{ $report->site->name }} &middot; {{ $report->dateRange()->label() }}</p>

                            @if ($customMessage)
                                <div style="font-size:15px; color:#33302b; margin-bottom:22px;">{!! nl2br(e($customMessage)) !!}</div>
                            @else
                                <p style="font-size:15px; color:#33302b; margin-bottom:22px;">Your latest website report is ready. Click below to view it.</p>
                            @endif

                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="border-radius:6px; background:{{ $branding->primaryColor }};">
                                        <a href="{{ $url }}" style="display:inline-block; padding:11px 22px; color:#ffffff; text-decoration:none; font-size:15px; font-weight:500;">View your report</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px; border-top:1px solid #eee7db; color:#98938a; font-size:12px;">
                            {{ $branding->emailFooter ?: $branding->agencyName }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
