<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Models\Cupon;
use App\Models\Cuenta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class CuponController extends Controller
{
    /**
     * Valida un código de cupón y devuelve el descuento calculado.
     * POST /tienda/cupones/validar
     * body: { codigo, subtotal }
     * Header Authorization opcional (para validar restricciones de cuenta)
     */
    public function validar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codigo'   => 'required|string|max:30',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $subtotal = (float) $data['subtotal'];

        $cupon = Cupon::where('codigo', strtoupper(trim($data['codigo'])))->first();

        if (!$cupon || !$cupon->estaVigente()) {
            $msg = ($cupon && $cupon->mensaje_error)
                ? $cupon->mensaje_error
                : 'Este cupón no es válido o ha expirado.';
            return response()->json(['success' => false, 'message' => $msg], 422);
        }

        // Verificar mínimo de compra
        if ($cupon->minimo_compra && $subtotal < (float) $cupon->minimo_compra) {
            return response()->json([
                'success' => false,
                'message' => "Este cupón requiere un mínimo de Q" . number_format($cupon->minimo_compra, 2) . " en compras.",
            ], 422);
        }

        // Detectar cuenta autenticada (opcional)
        $cuenta = $this->cuentaDelToken($request);

        // Validar restricciones de cuenta
        if ($cuenta) {
            // ¿Cupón privado solo para cuentas asignadas?
            if (!$cupon->es_publico) {
                $asignado = $cuenta->cupones()->where('cupon_id', $cupon->id)->first();
                if (!$asignado) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Este cupón no está disponible para tu cuenta.',
                    ], 422);
                }
                // Verificar usos por cuenta
                if ($asignado->pivot->usos >= $cupon->usos_por_cuenta) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya utilizaste este cupón el máximo de veces permitidas.',
                    ], 422);
                }
            }

            // Solo primera compra
            if ($cupon->solo_primera_compra && $cuenta->pedidos()->exists()) {
                $msg = $cupon->mensaje_error ?? 'Este cupón es exclusivo para tu primera compra.';
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
        } elseif (!$cupon->es_publico) {
            // Cupón privado pero sin sesión
            return response()->json([
                'success' => false,
                'message' => 'Debes iniciar sesión para usar este cupón.',
            ], 422);
        }

        $descuento = $cupon->calcularDescuento($subtotal);

        return response()->json([
            'success'         => true,
            'cupon_id'        => $cupon->id,
            'codigo'          => $cupon->codigo,
            'descripcion'     => $cupon->descripcion,
            'tipo'            => $cupon->tipo,
            'valor'           => $cupon->valor,
            'descuento'       => $descuento,
            'maximo_descuento'=> $cupon->maximo_descuento,
        ]);
    }

    /**
     * Lista los cupones asignados a la cuenta autenticada.
     * GET /tienda/cuenta/cupones  (requiere auth.cuenta)
     */
    public function misCupones(Request $request): JsonResponse
    {
        $cuenta = $request->user();

        $cupones = $cuenta->cupones()
            ->where('estado', 'activo')
            ->get()
            ->map(function ($cupon) {
                $usosUsados    = $cupon->pivot->usos ?? 0;
                $usosRestantes = $cupon->usos_por_cuenta - $usosUsados;
                $vigente       = $cupon->estaVigente() && $usosRestantes > 0;

                return [
                    'codigo'            => $cupon->codigo,
                    'descripcion'       => $cupon->descripcion,
                    'tipo'              => $cupon->tipo,
                    'valor'             => (float) $cupon->valor,
                    'minimo_compra'     => $cupon->minimo_compra ? (float) $cupon->minimo_compra : null,
                    'maximo_descuento'  => $cupon->maximo_descuento ? (float) $cupon->maximo_descuento : null,
                    'solo_primera_compra' => $cupon->solo_primera_compra,
                    'fecha_vencimiento' => $cupon->fecha_vencimiento?->format('d/m/Y'),
                    'usos_usados'       => $usosUsados,
                    'usos_restantes'    => $usosRestantes,
                    'vigente'           => $vigente,
                ];
            });

        return response()->json([
            'success' => true,
            'cupones' => $cupones,
        ]);
    }

    // ── Helper ─────────────────────────────────────────────────────────────────

    private function cuentaDelToken(Request $request): ?Cuenta
    {
        $bearer = $request->bearerToken();
        if (!$bearer) return null;

        $pat = PersonalAccessToken::findToken($bearer);
        if (!$pat || $pat->tokenable_type !== Cuenta::class) return null;

        return Cuenta::find($pat->tokenable_id);
    }
}