<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Resena;
use App\Models\ProductoPregunta;
use App\Services\PuntosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPedidoController extends Controller
{
    public function __construct(private PuntosService $puntos) {}

    /**
     * Listar pedidos de tienda con filtros.
     * GET /pedidos-tienda
     */
    public function index(Request $request): JsonResponse
    {
        $query = Pedido::with(['cuenta:id,nombre,apellido,email', 'detalles'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($q2) use ($q) {
                $q2->where('numero_pedido', 'LIKE', "%{$q}%")
                   ->orWhere('email', 'LIKE', "%{$q}%")
                   ->orWhere('nombre', 'LIKE', "%{$q}%");
            });
        }

        $pedidos = $query->paginate($request->get('per_page', 20));

        return response()->json(['success' => true, 'pedidos' => $pedidos]);
    }

    /**
     * Detalle de un pedido.
     * GET /pedidos-tienda/{id}
     */
    public function show(int $id): JsonResponse
    {
        $pedido = Pedido::with(['cuenta:id,nombre,apellido,email', 'detalles'])->find($id);

        if (!$pedido) {
            return response()->json(['success' => false, 'message' => 'Pedido no encontrado'], 404);
        }

        return response()->json(['success' => true, 'pedido' => $pedido]);
    }

    /**
     * Cambiar estado de un pedido y gestionar puntos automáticamente.
     * PATCH /pedidos-tienda/{id}/estado
     */
    /**
     * Estadísticas generales de la tienda para el dashboard.
     * GET /pedidos-tienda/estadisticas
     */
    public function estadisticas(): JsonResponse
    {
        $ahora     = now();
        $inicioMes = $ahora->copy()->startOfMonth();
        $hoy       = $ahora->copy()->startOfDay();

        // Pedidos por estado (totales históricos)
        $porEstado = Pedido::selectRaw('estado, COUNT(*) as total, SUM(total) as suma')
            ->groupBy('estado')
            ->get()
            ->keyBy('estado');

        // Pedidos de hoy
        $pedidosHoy = Pedido::where('created_at', '>=', $hoy)->count();

        // Pedidos del mes
        $pedidosMes = Pedido::where('created_at', '>=', $inicioMes)->count();

        // Ingresos (pedidos entregados del mes)
        $ingresosMes = (float) Pedido::where('created_at', '>=', $inicioMes)
            ->where('estado', 'entregado')
            ->sum('total');

        // Pendientes activos (estados que requieren acción)
        $pendientes = Pedido::whereIn('estado', ['pendiente', 'confirmado', 'en_preparacion', 'enviado'])->count();

        // Reseñas por estado
        $resenas = Resena::selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->get()
            ->keyBy('estado');

        // Preguntas por estado
        $preguntas = ProductoPregunta::selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->get()
            ->keyBy('estado');

        return response()->json([
            'success' => true,
            'estadisticas' => [
                'pedidos_hoy'  => $pedidosHoy,
                'pedidos_mes'  => $pedidosMes,
                'ingresos_mes' => $ingresosMes,
                'pendientes'   => $pendientes,
                'por_estado'   => [
                    'pendiente'      => (int) ($porEstado['pendiente']->total      ?? 0),
                    'confirmado'     => (int) ($porEstado['confirmado']->total     ?? 0),
                    'en_preparacion' => (int) ($porEstado['en_preparacion']->total ?? 0),
                    'enviado'        => (int) ($porEstado['enviado']->total        ?? 0),
                    'entregado'      => (int) ($porEstado['entregado']->total      ?? 0),
                    'cancelado'      => (int) ($porEstado['cancelado']->total      ?? 0),
                ],
                'resenas' => [
                    'pendiente' => (int) ($resenas['pendiente']->total ?? 0),
                    'publicado' => (int) ($resenas['publicado']->total ?? 0),
                    'rechazado' => (int) ($resenas['rechazado']->total ?? 0),
                ],
                'preguntas' => [
                    'pendiente'  => (int) ($preguntas['pendiente']->total  ?? 0),
                    'respondida' => (int) ($preguntas['respondida']->total ?? 0),
                    'rechazada'  => (int) ($preguntas['rechazada']->total  ?? 0),
                ],
            ],
        ]);
    }

    /**
     * Cambiar estado de un pedido y gestionar puntos automáticamente.
     * PATCH /pedidos-tienda/{id}/estado
     */
    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'estado' => 'required|in:pendiente,confirmado,en_preparacion,enviado,entregado,cancelado',
        ]);

        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json(['success' => false, 'message' => 'Pedido no encontrado'], 404);
        }

        $estadoAnterior = $pedido->estado;
        $estadoNuevo    = $request->estado;

        $pedido->update(['estado' => $estadoNuevo]);

        // Gestión de puntos por compra
        if ($pedido->cuenta_id) {
            if ($estadoNuevo === 'entregado' && $estadoAnterior !== 'entregado') {
                $this->puntos->otorgarPorCompra($pedido->fresh());
            }

            if ($estadoNuevo === 'cancelado' && $estadoAnterior === 'entregado') {
                $this->puntos->revertirPorCompra($pedido);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado.',
            'pedido'  => $pedido->fresh(),
        ]);
    }
}