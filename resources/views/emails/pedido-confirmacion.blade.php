<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de pedido #{{ $pedido->numero_pedido }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;-webkit-font-smoothing:antialiased;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);">

                {{-- ── Header ─────────────────────────────────────────── --}}
                <tr>
                    <td style="background:#16a34a;padding:28px 40px;text-align:center;">
                        <p style="margin:0;color:#bbf7d0;font-size:13px;letter-spacing:1px;text-transform:uppercase;font-weight:600;">
                            {{ config('app.name') }}
                        </p>
                        <h1 style="margin:6px 0 0;color:#ffffff;font-size:24px;font-weight:700;letter-spacing:-0.3px;">
                            ¡Pedido confirmado!
                        </h1>
                    </td>
                </tr>

                {{-- ── Número de pedido ────────────────────────────────── --}}
                <tr>
                    <td style="background:#f0fdf4;border-bottom:1px solid #dcfce7;padding:16px 40px;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>
                                    <p style="margin:0;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:0.8px;font-weight:600;">
                                        N° de pedido
                                    </p>
                                    <p style="margin:2px 0 0;font-size:20px;font-weight:700;color:#15803d;letter-spacing:0.5px;font-family:'Courier New',monospace;">
                                        {{ $pedido->numero_pedido }}
                                    </p>
                                </td>
                                <td style="text-align:right;vertical-align:middle;">
                                    <p style="margin:0;font-size:12px;color:#9ca3af;">
                                        {{ \Carbon\Carbon::parse($pedido->created_at)->setTimezone('America/Guatemala')->format('d/m/Y H:i') }}
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- ── Saludo ──────────────────────────────────────────── --}}
                <tr>
                    <td style="padding:28px 40px 0;">
                        <p style="margin:0;font-size:15px;color:#374151;line-height:1.6;">
                            Hola, <strong>{{ $pedido->nombre }}</strong>.
                        </p>
                        <p style="margin:8px 0 0;font-size:15px;color:#6b7280;line-height:1.6;">
                            Recibimos tu pedido y ya está siendo procesado.
                            @if($pedido->metodo_pago === 'tarjeta')
                                En breve nos pondremos en contacto contigo para enviarte el enlace de pago.
                            @elseif($pedido->metodo_pago === 'deposito_transferencia')
                                Recuerda subir tu comprobante de pago para confirmar tu pedido.
                            @endif
                        </p>
                    </td>
                </tr>

                {{-- ── Productos ───────────────────────────────────────── --}}
                <tr>
                    <td style="padding:24px 40px 0;">
                        <p style="margin:0 0 12px;font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.8px;">
                            Productos
                        </p>

                        @foreach($detalles as $d)
                        <table width="100%" cellpadding="0" cellspacing="0"
                               style="margin-bottom:{{ $loop->last ? '0' : '12px' }};border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                            <tr>
                                {{-- Imagen --}}
                                <td width="72" style="padding:12px;vertical-align:middle;background:#f9fafb;">
                                    @if($d['imagen'])
                                        <img src="{{ $d['imagen'] }}"
                                             alt="{{ $d['nombre_producto'] }}"
                                             width="56" height="56"
                                             style="display:block;object-fit:contain;border-radius:6px;background:#ffffff;">
                                    @else
                                        <table width="56" height="56" cellpadding="0" cellspacing="0"
                                               style="background:#e5e7eb;border-radius:6px;">
                                            <tr><td align="center" style="font-size:22px;">📦</td></tr>
                                        </table>
                                    @endif
                                </td>

                                {{-- Nombre + precio --}}
                                <td style="padding:12px 16px;vertical-align:middle;">
                                    @if($d['url'])
                                        <a href="{{ $d['url'] }}"
                                           style="font-size:14px;font-weight:600;color:#15803d;text-decoration:none;line-height:1.4;">
                                            {{ $d['nombre_producto'] }}
                                        </a>
                                    @else
                                        <span style="font-size:14px;font-weight:600;color:#1f2937;line-height:1.4;">
                                            {{ $d['nombre_producto'] }}
                                        </span>
                                    @endif
                                    <p style="margin:4px 0 0;font-size:12px;color:#9ca3af;">
                                        Q{{ number_format($d['precio_unitario'], 2) }} × {{ $d['cantidad'] }}
                                        {{ $d['cantidad'] == 1 ? 'unidad' : 'unidades' }}
                                    </p>
                                </td>

                                {{-- Subtotal --}}
                                <td style="padding:12px 16px;vertical-align:middle;text-align:right;white-space:nowrap;">
                                    <span style="font-size:15px;font-weight:700;color:#1f2937;font-family:'Courier New',monospace;">
                                        Q{{ number_format($d['subtotal'], 2) }}
                                    </span>
                                </td>
                            </tr>
                        </table>
                        @endforeach
                    </td>
                </tr>

                {{-- ── Resumen de pago ─────────────────────────────────── --}}
                <tr>
                    <td style="padding:24px 40px 0;">
                        <table width="100%" cellpadding="0" cellspacing="0"
                               style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                            <tr>
                                <td colspan="2" style="background:#f9fafb;padding:12px 16px;border-bottom:1px solid #e5e7eb;">
                                    <p style="margin:0;font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.8px;">
                                        Resumen de pago
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:10px 16px 4px;font-size:14px;color:#6b7280;">Subtotal</td>
                                <td style="padding:10px 16px 4px;font-size:14px;color:#374151;text-align:right;font-family:'Courier New',monospace;">
                                    Q{{ number_format($pedido->subtotal, 2) }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:4px 16px;font-size:14px;color:#6b7280;">Envío</td>
                                <td style="padding:4px 16px;font-size:14px;text-align:right;font-family:'Courier New',monospace;{{ $pedido->costo_envio == 0 ? 'color:#16a34a;font-weight:600;' : 'color:#374151;' }}">
                                    {{ $pedido->costo_envio == 0 ? 'Gratis' : 'Q' . number_format($pedido->costo_envio, 2) }}
                                </td>
                            </tr>
                            @if($pedido->descuento_cupon > 0)
                            <tr>
                                <td style="padding:4px 16px;font-size:14px;color:#16a34a;">Descuento cupón</td>
                                <td style="padding:4px 16px;font-size:14px;color:#16a34a;text-align:right;font-family:'Courier New',monospace;">
                                    -Q{{ number_format($pedido->descuento_cupon, 2) }}
                                </td>
                            </tr>
                            @endif
                            <tr>
                                <td style="padding:10px 16px;font-size:15px;font-weight:700;color:#1f2937;border-top:1px solid #e5e7eb;">
                                    Total
                                </td>
                                <td style="padding:10px 16px;font-size:17px;font-weight:700;color:#16a34a;text-align:right;border-top:1px solid #e5e7eb;font-family:'Courier New',monospace;">
                                    Q{{ number_format($pedido->total, 2) }}
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" style="padding:8px 16px 12px;font-size:12px;color:#9ca3af;border-top:1px solid #f3f4f6;">
                                    @php
                                        $metodoLabel = [
                                            'efectivo'               => 'Efectivo contra entrega',
                                            'deposito_transferencia' => 'Depósito / Transferencia',
                                            'tarjeta'                => 'Tarjeta',
                                            'mixto'                  => 'Mixto',
                                        ];
                                    @endphp
                                    <strong>Método de pago:</strong>
                                    {{ $metodoLabel[$pedido->metodo_pago] ?? $pedido->metodo_pago }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- ── Entrega ─────────────────────────────────────────── --}}
                <tr>
                    <td style="padding:16px 40px 0;">
                        <table width="100%" cellpadding="0" cellspacing="0"
                               style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                            <tr>
                                <td style="background:#f9fafb;padding:12px 16px;border-bottom:1px solid #e5e7eb;">
                                    <p style="margin:0;font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.8px;">
                                        Entrega
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:12px 16px;">
                                    <p style="margin:0;font-size:14px;font-weight:600;color:#1f2937;">{{ $pedido->nombre }}</p>
                                    <p style="margin:3px 0 0;font-size:13px;color:#6b7280;">{{ $pedido->telefono }}</p>
                                    <p style="margin:3px 0 0;font-size:13px;color:#6b7280;">
                                        {{ $pedido->direccion }}, {{ $pedido->municipio }}, {{ $pedido->departamento }}
                                    </p>
                                    @if($pedido->referencias)
                                    <p style="margin:3px 0 0;font-size:12px;color:#9ca3af;">{{ $pedido->referencias }}</p>
                                    @endif
                                    @if($pedido->notas)
                                    <p style="margin:8px 0 0;font-size:12px;color:#6b7280;">
                                        <strong>Notas:</strong> {{ $pedido->notas }}
                                    </p>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- ── Mensaje pago con tarjeta ────────────────────────── --}}
                @if($pedido->metodo_pago === 'tarjeta')
                <tr>
                    <td style="padding:16px 40px 0;">
                        <table width="100%" cellpadding="0" cellspacing="0"
                               style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;">
                            <tr>
                                <td style="padding:14px 16px;">
                                    <p style="margin:0;font-size:13px;font-weight:600;color:#1e40af;">Pago con tarjeta</p>
                                    <p style="margin:4px 0 0;font-size:13px;color:#1d4ed8;line-height:1.5;">
                                        Nos comunicaremos contigo pronto por WhatsApp o por correo para enviarte
                                        el enlace de pago seguro.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                @endif

                {{-- ── Mensaje depósito/transferencia ─────────────────── --}}
                @if($pedido->metodo_pago === 'deposito_transferencia')
                <tr>
                    <td style="padding:16px 40px 0;">
                        <table width="100%" cellpadding="0" cellspacing="0"
                               style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;">
                            <tr>
                                <td style="padding:14px 16px;">
                                    <p style="margin:0;font-size:13px;font-weight:600;color:#92400e;">Recuerda subir tu comprobante</p>
                                    <p style="margin:4px 0 0;font-size:13px;color:#b45309;line-height:1.5;">
                                        Al realizar la transferencia incluye el número
                                        <strong style="font-family:'Courier New',monospace;">{{ $pedido->numero_pedido }}</strong>
                                        en la descripción. Puedes enviarlo también por WhatsApp al
                                        <strong>4710 4888</strong>.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                @endif

                {{-- ── CTA ver pedido ──────────────────────────────────── --}}
                <tr>
                    <td style="padding:28px 40px;text-align:center;">
                        <a href="{{ $storeUrl }}/cuenta/pedidos/{{ $pedido->numero_pedido }}"
                           style="display:inline-block;background:#16a34a;color:#ffffff;text-decoration:none;padding:13px 32px;border-radius:8px;font-size:14px;font-weight:700;letter-spacing:0.3px;">
                            Ver mi pedido
                        </a>
                        <p style="margin:12px 0 0;font-size:12px;color:#9ca3af;">
                            O accede desde
                            <a href="{{ $storeUrl }}/cuenta/pedidos"
                               style="color:#16a34a;text-decoration:none;">
                                Mis pedidos
                            </a>
                            en tu cuenta.
                        </p>
                    </td>
                </tr>

                {{-- ── Footer ─────────────────────────────────────────── --}}
                <tr>
                    <td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;">
                        <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.6;">
                            Este correo fue enviado automáticamente al realizar tu pedido.<br>
                            Si tienes dudas contáctanos al <strong style="color:#6b7280;">4710 4888</strong>
                            o escríbenos a <a href="mailto:soporte@logickem.com" style="color:#16a34a;text-decoration:none;">soporte@logickem.com</a>
                        </p>
                        <p style="margin:10px 0 0;font-size:11px;color:#d1d5db;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
