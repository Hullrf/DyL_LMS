<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
</head>
<body style="margin:0;padding:0;background-color:#F8FAFC;font-family:Arial,Helvetica,sans-serif;color:#0F172A;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F8FAFC;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background-color:#FFFFFF;border:1px solid #E2E8F0;border-radius:16px;">
                    <tr>
                        <td style="padding:28px 32px 8px 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding-right:12px;">
                                        <img src="{{ asset('images/logo/logo-icon-dark-256.png') }}" width="44" height="44" alt="" style="display:block;border:0;">
                                    </td>
                                    <td style="font-size:15px;font-weight:bold;color:#0F172A;">D&amp;L Quality Campus</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 8px 32px;">
                            <h1 style="margin:0 0 12px 0;font-family:Georgia,'Times New Roman',serif;font-size:24px;line-height:1.3;font-weight:normal;color:#0F172A;">{{ $titulo }}</h1>
                            <p style="margin:0;font-size:16px;line-height:1.6;color:#334155;">{{ $mensaje }}</p>
                        </td>
                    </tr>
                    @if ($url)
                        <tr>
                            <td style="padding:20px 32px 8px 32px;">
                                <a href="{{ $url }}" style="display:inline-block;background-color:#C2410C;color:#FFFFFF;text-decoration:none;font-size:15px;font-weight:bold;padding:12px 22px;border-radius:10px;">Abrir en el campus</a>
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:24px 32px 28px 32px;">
                            <p style="margin:0;padding-top:16px;border-top:1px solid #E2E8F0;font-size:12px;line-height:1.5;color:#64748B;">
                                Recibes este correo porque tienes una cuenta en D&amp;L Quality Campus.
                                <a href="{{ $perfilUrl }}" style="color:#C2410C;">Elige qué avisos recibir en tu perfil.</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
