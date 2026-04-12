<?php

namespace App\Http\Controllers;

use App\Models\CuentaPunto;
use App\Services\PuntosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CuentaPuntosController extends Controller
{
    public function __construct(private PuntosService $puntos) {}

    /**
     * Saldo e historial de puntos del usuario autenticado.
     * GET /tienda/cuenta/puntos
     */
    public function index(Request $request): JsonResponse
    {
        $cuenta = $request->user();

        $historial = CuentaPunto::where('cuenta_id', $cuenta->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn($m) => [
                'id'          => $m->id,
                'tipo'        => $m->tipo,
                'puntos'      => $m->puntos,
                'descripcion' => $m->concepto,
                'fecha'       => $m->created_at->format('d/m/Y'),
            ]);

        return response()->json([
            'success'         => true,
            'saldo'           => $cuenta->puntos_saldo,
            'opciones_canje'  => PuntosService::opcionesCanje(),
            'historial'       => $historial,
        ]);
    }

    /**
     * Canjear puntos por cupón.
     * POST /tienda/cuenta/puntos/canjear
     */
    public function canjear(Request $request): JsonResponse
    {
        $request->validate([
            'puntos' => 'required|integer',
        ]);

        $cuenta = $request->user();

        try {
            $cupon = $this->puntos->canjear($cuenta, $request->puntos);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "¡Cupón generado! Úsalo en tu próxima compra.",
            'cupon'   => [
                'codigo'      => $cupon->codigo,
                'valor'       => (float) $cupon->valor,
                'fecha_fin'   => $cupon->fecha_vencimiento?->format('d/m/Y'),
            ],
            'nuevo_saldo' => $cuenta->fresh()->puntos_saldo,
        ]);
    }
}