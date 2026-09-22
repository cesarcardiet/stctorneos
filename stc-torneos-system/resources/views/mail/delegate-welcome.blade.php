<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Acceso delegado · STC Torneos</title>
</head>
<body style="margin:0;padding:24px;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#10233f;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #d8e3f2;">
        <tr>
            <td style="padding:24px 28px;background:#0f2f57;color:#ffffff;">
                <p style="margin:0 0 6px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.8;">STC Torneos</p>
                <h1 style="margin:0;font-size:22px;line-height:1.3;">Acceso de delegado</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:28px;">
                <p style="margin:0 0 16px;font-size:16px;line-height:1.5;">Hola <strong>{{ $user->name }}</strong>,</p>
                <p style="margin:0 0 20px;font-size:15px;line-height:1.6;">
                    Quedaste asignado/a como delegado/a de <strong>{{ $club->name }}</strong> en <strong>{{ $tournament->name }}</strong>.
                </p>

                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;background:#f7faff;border:1px solid #dbe7f7;border-radius:12px;">
                    <tr>
                        <td style="padding:18px 20px;">
                            <p style="margin:0 0 10px;font-size:13px;font-weight:bold;text-transform:uppercase;letter-spacing:.06em;color:#4b6280;">Datos para ingresar</p>
                            <p style="margin:0 0 8px;font-size:15px;line-height:1.5;"><strong>Correo:</strong> {{ $user->email }}</p>
                            @if ($password)
                                <p style="margin:0 0 8px;font-size:15px;line-height:1.5;"><strong>Clave:</strong> {{ $password }}</p>
                            @else
                                <p style="margin:0 0 8px;font-size:15px;line-height:1.5;"><strong>Clave:</strong> la que ya tenés configurada.</p>
                            @endif
                            <p style="margin:0;font-size:15px;line-height:1.5;"><strong>Ingreso:</strong> <a href="{{ $loginUrl }}" style="color:#1f6feb;">{{ $loginUrl }}</a></p>
                        </td>
                    </tr>
                </table>

                <p style="margin:0 0 24px;font-size:15px;line-height:1.6;">
                    Con este acceso solo vas a ver <strong>{{ $club->name }}</strong>. Podés cambiar la clave desde tu panel cuando quieras.
                </p>

                <p style="margin:0;">
                    <a href="{{ $loginUrl }}" style="display:inline-block;padding:12px 18px;background:#1f6feb;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:bold;">Ir al login</a>
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:18px 28px;background:#f7faff;border-top:1px solid #e3ebf6;font-size:12px;line-height:1.5;color:#5b6f88;">
                Este mensaje fue generado automáticamente al crear tu acceso de delegado.
            </td>
        </tr>
    </table>
</body>
</html>
