<?php

namespace App\Http\Controllers;

use App\Models\Resena;
use App\Models\ProductoPregunta;
use App\Services\PuntosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminResenaController extends Controller
{
    public function __construct(private PuntosService $puntos) {}

    /**
     * Listar reseñas con filtros.
     * GET /admin/resenas
     */
    public function index(Request $request): JsonResponse
    {
        $query = Resena::with(['cuenta:id,nombre,apellido,email', 'producto:id,nombre,precio_venta'])
            ->orderBy('created_at', 'desc');

        $countQuery = clone $query;

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $resenas  = $query->paginate($request->get('per_page', 20));
        $byEstado = $countQuery->reorder()->selectRaw('estado, COUNT(*) as cnt')->groupBy('estado')->pluck('cnt', 'estado');

        $pend = (int) ($byEstado['pendiente'] ?? 0);
        $pub  = (int) ($byEstado['publicado'] ?? 0);
        $rech = (int) ($byEstado['rechazado'] ?? 0);

        return response()->json([
            'success' => true,
            'resenas' => $resenas,
            'counts'  => [
                'total'     => $pend + $pub + $rech,
                'pendiente' => $pend,
                'publicado' => $pub,
                'rechazado' => $rech,
            ],
        ]);
    }

    /**
     * Cambiar estado de una reseña (publicado / rechazado).
     * PATCH /admin/resenas/{id}/estado
     */
    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'estado' => 'required|in:publicado,rechazado,pendiente',
        ]);

        $resena = Resena::with('producto')->find($id);

        if (!$resena) {
            return response()->json(['success' => false, 'message' => 'Reseña no encontrada'], 404);
        }

        $estadoAnterior = $resena->estado;
        $estadoNuevo    = $request->estado;

        $resena->update(['estado' => $estadoNuevo]);

        // Gestión de puntos por reseña
        if ($resena->cuenta_id) {
            if ($estadoNuevo === 'publicado' && $estadoAnterior !== 'publicado') {
                $this->puntos->otorgarPorResena($resena);
            }

            if ($estadoNuevo === 'rechazado' && $estadoAnterior === 'publicado') {
                $this->puntos->revertirPorResena($resena);
            }
        }

        return response()->json(['success' => true, 'message' => 'Estado actualizado.']);
    }

    // ── Preguntas ──────────────────────────────────────────────────────────────

    /**
     * Listar preguntas con filtros.
     * GET /admin/preguntas
     */
    public function preguntasIndex(Request $request): JsonResponse
    {
        $query = ProductoPregunta::with(['cuenta:id,nombre,apellido', 'producto:id,nombre'])
            ->orderBy('created_at', 'desc');

        $countQuery = clone $query;

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $preguntas = $query->paginate($request->get('per_page', 20));
        $byEstado  = $countQuery->reorder()->selectRaw('estado, COUNT(*) as cnt')->groupBy('estado')->pluck('cnt', 'estado');

        $pend = (int) ($byEstado['pendiente']  ?? 0);
        $resp = (int) ($byEstado['respondida'] ?? 0);
        $rech = (int) ($byEstado['rechazada']  ?? 0);

        return response()->json([
            'success'   => true,
            'preguntas' => $preguntas,
            'counts'    => [
                'total'      => $pend + $resp + $rech,
                'pendiente'  => $pend,
                'respondida' => $resp,
                'rechazada'  => $rech,
            ],
        ]);
    }

    /**
     * Responder / rechazar una pregunta.
     * PATCH /admin/preguntas/{id}
     */
    public function preguntaUpdate(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'estado'    => 'required|in:respondida,rechazada,pendiente',
            'respuesta' => 'required_if:estado,respondida|nullable|string|max:1000',
        ]);

        $pregunta = ProductoPregunta::find($id);

        if (!$pregunta) {
            return response()->json(['success' => false, 'message' => 'Pregunta no encontrada'], 404);
        }

        $pregunta->update([
            'estado'    => $request->estado,
            'respuesta' => $request->estado === 'respondida' ? $request->respuesta : $pregunta->respuesta,
        ]);

        return response()->json(['success' => true, 'message' => 'Pregunta actualizada.']);
    }
}