<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\ProductoPregunta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreguntaController extends Controller
{
    /**
     * GET /tienda/productos/{id}/preguntas
     * Lista pública de preguntas respondidas de un producto.
     */
    public function index(int $id): JsonResponse
    {
        $preguntas = ProductoPregunta::where('producto_id', $id)
            ->where('estado', 'respondida')
            ->latest()
            ->get()
            ->map(fn($p) => [
                'id'         => $p->id,
                'pregunta'   => $p->pregunta,
                'respuesta'  => $p->respuesta,
                'created_at' => $p->created_at->format('d/m/Y'),
            ]);

        return response()->json([
            'success'   => true,
            'preguntas' => $preguntas,
        ]);
    }

    /**
     * POST /tienda/productos/{id}/preguntas  (auth.cuenta)
     */
    public function store(Request $request, int $id): JsonResponse
    {
        if (!Producto::where('estado', 'activo')->find($id)) {
            return response()->json(['success' => false, 'message' => 'Producto no encontrado.'], 404);
        }

        $data = $request->validate([
            'pregunta' => 'required|string|max:500',
        ]);

        ProductoPregunta::create([
            'producto_id' => $id,
            'cuenta_id'   => $request->user()->id,
            'pregunta'    => $data['pregunta'],
            'estado'      => 'pendiente',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tu pregunta fue enviada. Te notificaremos cuando sea respondida.',
        ], 201);
    }
}
