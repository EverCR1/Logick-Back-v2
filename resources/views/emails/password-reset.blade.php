<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperación de contraseña</title>
    <style>
        body { margin: 0; padding: 0; background: #f4f4f4; font-family: Arial, sans-serif; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #16a34a; padding: 32px 40px; text-align: center; }
        .header h1 { margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: 0.5px; }
        .body { padding: 36px 40px; color: #374151; }
        .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; }
        .btn-wrap { text-align: center; margin: 28px 0; }
        .btn { display: inline-block; background: #16a34a; color: #ffffff !important; text-decoration: none; padding: 13px 32px; border-radius: 6px; font-size: 15px; font-weight: 600; }
        .url-fallback { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px 16px; word-break: break-all; font-size: 13px; color: #6b7280; margin-top: 8px; }
        .footer { background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 20px 40px; text-align: center; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ config('app.name') }}</h1>
        </div>
        <div class="body">
            <p>Hola, <strong>{{ $user->name }}</strong>.</p>
            <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta. Haz clic en el botón para continuar:</p>

            <div class="btn-wrap">
                <a href="{{ $resetUrl }}" class="btn">Restablecer contraseña</a>
            </div>

            <p>Si el botón no funciona, copia y pega el siguiente enlace en tu navegador:</p>
            <div class="url-fallback">{{ $resetUrl }}</div>

            <p style="margin-top:24px;">Este enlace expirará en <strong>60 minutos</strong>. Si no solicitaste este cambio, puedes ignorar este correo — tu contraseña no será modificada.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
