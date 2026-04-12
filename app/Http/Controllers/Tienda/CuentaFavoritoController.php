<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CuentaFavoritoController extends Controller
{
    /**
     * GET /tienda/cuenta/favoritos
     */
    public function index(Request $request): JsonResponse
    {
        $favoritos = $request->user()
            ->favoritos()
            ->with('imagenPrincipal')
            ->orderByPivot('created_at', 'desc')
            ->get()
            ->map(fn($p) => [
                'id'             => $p->id,
                'nombre'         => $p->nombre,
                'marca'          => $p->marca,
                'precio_venta'   => $p->precio_venta,
                'precio_oferta'  => $p->precio_oferta,
                'imagen_principal'=> $p->imagenPrincipal?->url_thumb ?? $p->imagenPrincipal?->url ?? null,
            ]);

        return response()->json(['success' => true, 'favoritos' => $favoritos]);
    }

    /**
     * POST /tienda/cuenta/favoritos
     * body: { producto_id }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'producto_id' => 'required|integer|exists:productos,id',
        ]);

        // syncWithoutDetaching evita duplicados sin lanzar error
        $request->user()->favoritos()->syncWithoutDetaching([$data['producto_id']]);

        return response()->json(['success' => true], 201);
    }

    /**
     * DELETE /tienda/cuenta/favoritos/{productoId}
     */
    public function destroy(Request $request, int $productoId): JsonResponse
    {
        $request->user()->favoritos()->detach($productoId);

        return response()->json(['success' => true]);
    }
}