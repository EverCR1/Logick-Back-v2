<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Resena;
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