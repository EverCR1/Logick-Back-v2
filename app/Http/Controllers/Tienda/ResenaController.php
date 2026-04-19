<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\Resena;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResenaController extends Controller
{
    /**
     * GET /tienda/productos/{id}/resenas
     * Lista pública de reseñas publicadas de un producto.
     */
    public function index(int $id): JsonResponse
    {
        $resenas = Resena::with('cuenta:id,nombre,apellido')
            ->where('producto_id', $id)
            ->where('estado', 'publicado')
            ->latest()
            ->get()
            ->map(fn($r) => [
                'id'           => $r->id,
                'rating'       => $r->rating,
                'comentario'   => $r->comentario,
                'autor'        => trim($r->cuenta->nombre . ' ' . ($r->cuenta->apellido ?? '')),
                'created_at'   => $r->created_at->format('d/m/Y'),
            ]);

        $promedio = $resenas->avg('rating');
        $total    = $resenas->count();

        return response()->json([
            'success'  => true,
            'promedio' => $total > 0 ? round($promedio, 1) : null,
            'total'    => $total,
            'resenas'  => $resenas,
        ]);
    }

    /**
     * GET /tienda/productos/{id}/resenas/mia  (auth.cuenta)
     * Devuelve la reseña del usuario autenticado para este producto, si existe.
     */
    public function mia(Request $request, int $id): JsonResponse
    {
        $resena = Resena::where('cuenta_id', $request->user()->id)
            ->where('producto_id', $id)
            ->first();

        return response()->json([
            'success'         => true,
            'resena'          => $resena ? [
                'id'        => $resena->id,
                'rating'    => $resena->rating,
                'comentario'=> $resena->comentario,
                'estado'    => $resena->estado,
            ] : null,
            'puede_resenar'   => $this->puedeResenar($request->user()->id, $id),
        ]);
    }

    /**
     * POST /tienda/productos/{id}/resenas  (auth.cuenta)
     */
    public function store(Request $request, int $id): JsonResponse
    {
        if (!Producto::where('estado', 'activo')->find($id)) {
            return response()->json(['success' => false, 'message' => 'Producto no encontrado.'], 404);
        }

        $cuentaId = $request->user()->id;

        if (Resena::where('cuenta_id', $cuentaId)->where('producto_id', $id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Ya dejaste una reseña para este producto.'], 422);
        }

        if (!$this->puedeResenar($cuentaId, $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Solo puedes reseñar productos que hayas recibido.',
            ], 422);
        }

        $data = $request->validate([
            'rating'     => 'required|integer|min:1|max:5',
            'comentario' => 'nullable|string|max:1000',
        ]);

        Resena::create([
            'cuenta_id'   => $cuentaId,
            'producto_id' => $id,
            'rating'      => $data['rating'],
            'comentario'  => $data['comentario'] ?? null,
            'estado'      => 'pendiente',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tu reseña fue enviada y será publicada tras una breve revisión.',
        ], 201);
    }

    // ── Helper ─────────────────────────────────────────────────────────────────

    private function puedeResenar(int $cuentaId, int $productoId): bool
    {
        return PedidoDetalle::where('producto_id', $productoId)
            ->whereHas('pedido', fn($q) => $q
                ->where('cuenta_id', $cuentaId)
                ->where('estado', 'entregado')
            )
            ->exists();
    }
}
