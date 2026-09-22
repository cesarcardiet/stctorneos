<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subjectLine ?? 'STC Torneos' }}</title>
</head>
<body style="margin:0;padding:0;background:#020714;font-family:Arial,Helvetica,sans-serif;color:#e8eef8;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#020714;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#0a1628;border:1px solid #1a3a5c;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 28px 12px;text-align:center;">
                            <div style="font-size:12px;font-weight:700;letter-spacing:.12em;color:#00d5ff;text-transform:uppercase;">STC Torneos</div>
                            @isset($heading)
                                <h1 style="margin:12px 0 0;font-size:22px;line-height:1.3;color:#ffffff;">{{ $heading }}</h1>
                            @endisset
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 24px;font-size:15px;line-height:1.6;color:#c8d4e6;">
                            {{ $slot }}
                        </td>
                    </tr>
                    @isset($actionUrl)
                        <tr>
                            <td style="padding:0 28px 28px;text-align:center;">
                                <a href="{{ $actionUrl }}" style="display:inline-block;padding:12px 22px;border-radius:999px;background:linear-gradient(135deg,#006bff,#00d5ff);color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;">{{ $actionLabel ?? 'Abrir enlace' }}</a>
                            </td>
                        </tr>
                    @endisset
                    <tr>
                        <td style="padding:16px 28px 24px;border-top:1px solid #1a3a5c;font-size:12px;line-height:1.5;color:#8290a7;text-align:center;">
                            {{ $footer ?? 'Este mensaje fue enviado por STC Torneos.' }}<br>
                            <a href="{{ config('app.url') }}" style="color:#00d5ff;">{{ config('app.url') }}</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
