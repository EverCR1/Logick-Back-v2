<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Mail\PedidoConfirmacionMail;
use App\Models\Cuenta;
use App\Models\Cupon;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Services\ImgBBService;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class PedidoController extends Controller
{
    const COSTO_ENVIO_BASE    = 35.00;
    const MINIMO_ENVIO_GRATIS = 500.00;

    // Métodos que requieren comprobante obligatorio
    const METODOS_CON_COMPROBANTE = ['deposito_transferencia'];

    protected ImgBBService $imgBB;

    public function __construct(ImgBBService $imgBB)
    {
        $this->imgBB = $imgBB;
    }

    /**
     * Crear un nuevo pedido desde la tienda.
     * POST /tienda/pedidos  (application/json)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre'       => 'required|string|max:150',
            'telefono'     => 'required|string|max:20',
            'email'        => 'required|email|max:150',
            'departamento' => 'required|string|max:100',
            'municipio'    => 'required|string|max:100',
            'direccion'    => 'required|string|max:255',
            'referencias'  => 'nullable|string|max:255',
            'metodo_pago'  => 'required|in:efectivo,deposito_transferencia,tarjeta,mixto',
            'notas'        => 'nullable|string|max:500',
            'cupon_codigo' => 'nullable|string|max:30',
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|integer',
            'items.*.cantidad' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // ── Verificar productos y calcular subtotal ───────────────────
            $subtotal = 0;
            $detalles = [];

            foreach ($request->items as $item) {
                $producto = Producto::where('estado', 'activo')->find($item['id']);

                if (!$producto) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Producto ID {$item['id']} no disponible",
                    ], 422);
                }

                $precio    = (float) ($producto->precio_oferta ?? $producto->precio_venta);
                $itemTotal = round($precio * (int) $item['cantidad'], 2);
                $subtotal += $itemTotal;

                $detalles[] = [
                    'producto_id'     => $producto->id,
                    'nombre_producto' => $producto->nombre,
                    'precio_unitario' => $precio,
                    'cantidad'        => (int) $item['cantidad'],
                    'subtotal'        => $itemTotal,
                ];
            }

            $costoEnvio = $subtotal >= self::MINIMO_ENVIO_GRATIS ? 0 : self::COSTO_ENVIO_BASE;

            // ── Aplicar cupón (opcional) ──────────────────────────────────
            $cupon          = null;
            $descuentoCupon = 0;

            if ($request->filled('cupon_codigo')) {
                $cupon = Cupon::where('codigo', strtoupper(trim($request->cupon_codigo)))
                              ->where('estado', 'activo')
                              ->first();

                if ($cupon && $cupon->estaVigente()) {
                    $descuentoCupon = $cupon->calcularDescuento($subtotal);
                }
            }

            $total = round($subtotal + $costoEnvio - $descuentoCupon, 2);

            // ── Detectar cuenta autenticada (opcional) ────────────────────
            $cuentaId = null;
            $bearerToken = $request->bearerToken();
            if ($bearerToken) {
                $pat = PersonalAccessToken::findToken($bearerToken);
                if ($pat && $pat->tokenable_type === Cuenta::class) {
                    $cuentaId = $pat->tokenable_id;
                }
            }

            // ── Crear pedido ──────────────────────────────────────────────
            $pedido = Pedido::create([
                'numero_pedido' => Pedido::generarNumero(),
                'nombre'        => $request->nombre,
                'telefono'      => $request->telefono,
                'email'         => $request->email,
                'cliente_id'    => null,
                'cuenta_id'      => $cuentaId,
                'cupon_id'       => $cupon?->id,
                'descuento_cupon'=> $descuentoCupon,
                'departamento'   => $request->departamento,
                'municipio'     => $request->municipio,
                'direccion'     => $request->direccion,
                'referencias'   => $request->referencias,
                'metodo_pago'   => $request->metodo_pago,
                'notas'         => $request->notas,
                'subtotal'      => $subtotal,
                'costo_envio'   => $costoEnvio,
                'total'         => $total,
                'estado'        => 'pendiente',
            ]);

            foreach ($detalles as $detalle) {
                $pedido->detalles()->create($detalle);
            }

            // Registrar uso del cupón
            if ($cupon && $descuentoCupon > 0) {
                $cupon->increment('usos_actuales');

                if ($cuentaId) {
                    $pivot = $cupon->cuentas()->where('cuenta_id', $cuentaId)->first();
                    if ($pivot) {
                        $cupon->cuentas()->updateExistingPivot($cuentaId, ['usos' => $pivot->pivot->usos + 1]);
                    } else {
                        $cupon->cuentas()->attach($cuentaId, ['usos' => 1]);
                    }
                }
            }

            DB::commit();

            // ── Enviar correo de confirmación al cliente ───────────────
            try {
                Mail::to($pedido->email)->send(new PedidoConfirmacionMail($pedido));
            } catch (\Exception $e) {
                Log::warning('PedidoController@store — no se pudo enviar email: ' . $e->getMessage());
            }

            return response()->json([
                'success'            => true,
                'message'            => 'Pedido creado exitosamente',
                'numero_pedido'      => $pedido->numero_pedido,
                'total'              => $pedido->total,
                'costo_envio'        => $pedido->costo_envio,
                'requiere_comprobante' => in_array($pedido->metodo_pago, self::METODOS_CON_COMPROBANTE),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PedidoController@store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el pedido. Intenta de nuevo.',
            ], 500);
        }
    }

    /**
     * Subir comprobante de pago a un pedido existente.
     * POST /tienda/pedidos/{numero}/comprobante  (multipart/form-data)
     */
    public function subirComprobante(Request $request, string $numero): JsonResponse
    {
        $pedido = Pedido::where('numero_pedido', $numero)->first();

        if (!$pedido) {
            return response()->json(['success' => false, 'message' => 'Pedido no encontrado'], 404);
        }

        if ($pedido->comprobante_url) {
            return response()->json(['success' => false, 'message' => 'Este pedido ya tiene un comprobante registrado'], 422);
        }

        $validator = Validator::make($request->all(), [
            'comprobante' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ], [
            'comprobante.required' => 'Selecciona una imagen como comprobante.',
            'comprobante.image'    => 'El archivo debe ser una imagen.',
            'comprobante.max'      => 'El comprobante no debe superar los 5 MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Archivo inválido',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $result = $this->imgBB->uploadImage(
            $request->file('comprobante'),
            'comprobante-' . $pedido->numero_pedido
        );

        if (!$result['success']) {
            Log::error('PedidoController@subirComprobante: error ImgBB', $result);
            return response()->json([
                'success' => false,
                'message' => 'No se pudo subir el comprobante. Intenta de nuevo.',
            ], 500);
        }

        $pedido->update([
            'comprobante_url'      => $result['data']['url'] ?? null,
            'comprobante_imgbb_id' => $result['data']['id']  ?? null,
            'estado'               => 'confirmado',
        ]);

        return response()->json([
            'success'         => true,
            'message'         => 'Comprobante recibido. Tu pedido ha sido confirmado.',
            'comprobante_url' => $pedido->comprobante_url,
        ]);
    }

    /**
     * Consultar estado de un pedido por número.
     * GET /tienda/pedidos/{numero}
     */
    public function show(string $numero): JsonResponse
    {
        $pedido = Pedido::with('detalles')
            ->where('numero_pedido', $numero)
            ->first();

        if (!$pedido) {
            return response()->json(['success' => false, 'message' => 'Pedido no encontrado'], 404);
        }

        return response()->json([
            'success' => true,
            'pedido'  => [
                'numero_pedido'   => $pedido->numero_pedido,
                'estado'          => $pedido->estado,
                'nombre'          => $pedido->nombre,
                'total'           => $pedido->total,
                'costo_envio'     => $pedido->costo_envio,
                'metodo_pago'     => $pedido->metodo_pago,
                'comprobante_url' => $pedido->comprobante_url,
                'created_at'      => $pedido->created_at->format('d/m/Y H:i'),
                'detalles'        => $pedido->detalles->map(fn($d) => [
                    'nombre_producto' => $d->nombre_producto,
                    'cantidad'        => $d->cantidad,
                    'precio_unitario' => $d->precio_unitario,
                    'subtotal'        => $d->subtotal,
                ]),
            ],
        ]);
    }
}