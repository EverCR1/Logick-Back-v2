<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    /**
     * Árbol de categorías activas para la tienda pública.
     * GET /tienda/categorias
     */
    public function tree(): JsonResponse
    {
        try {
            $categorias = Categoria::with('childrenRecursive')
                ->whereNull('parent_id')
                ->where('estado', 'activo')
                ->orderBy('nombre')
                ->get()
                ->map(fn($c) => $this->formatear($c));

            return response()->json(['success' => true, 'categorias' => $categorias]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener categorías'], 500);
        }
    }

    /**
     * Sugerencias de categorías para el buscador.
     * GET /tienda/categorias/buscar?q=texto
     * Lógica OR por palabra: una categoría aparece si su nombre contiene
     * al menos una de las palabras del query.
     */
    public function buscar(Request $request): JsonResponse
    {
        $q = trim($request->get('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'categorias' => []]);
        }

        try {
            $palabras = array_filter(explode(' ', preg_replace('/\s+/', ' ', $q)));

            $categorias = Categoria::with('imagen')
                ->where('estado', 'activo')
                ->where(function ($query) use ($palabras) {
                    foreach ($palabras as $palabra) {
                        $termino = mb_strtolower($palabra);
                        // Mismo stemming de género que en productos
                        if (mb_strlen($termino) >= 4 && preg_match('/[oa]$/i', $termino)) {
                            $termino = mb_substr($termino, 0, -1);
                        }
                        $query->orWhere('nombre', 'LIKE', "%{$termino}%");
                    }
                })
                ->orderByRaw('parent_id IS NOT NULL') // padres primero
                ->limit(10) // traer más antes de deduplicar
                ->get()
                ->unique('nombre')  // evitar duplicados por nombre (ej: seeder corrido dos veces)
                ->take(5)
                ->values()
                ->map(fn($c) => [
                    'id'        => $c->id,
                    'nombre'    => $c->nombre,
                    'parent_id' => $c->parent_id,
                    'imagen'    => $c->imagen?->url_thumb ?? $c->imagen?->url ?? null,
                ]);

            return response()->json(['success' => true, 'categorias' => $categorias]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al buscar categorías'], 500);
        }
    }

    private function formatear(Categoria $cat): array
    {
        return [
            'id'                  => $cat->id,
            'nombre'              => $cat->nombre,
            'parent_id'           => $cat->parent_id,
            'children_recursive'  => $cat->childrenRecursive
                ->where('estado', 'activo')
                ->sortBy('nombre')
                ->values()
                ->map(fn($c) => $this->formatear($c))
                ->toArray(),
        ];
    }
}