<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CuentaPedidoController extends Controller
{
    /**
     * GET /tienda/cuenta/pedidos
     */
    public function index(Request $request): JsonResponse
    {
        $pedidos = $request->user()
            ->pedidos()
            ->select('id', 'numero_pedido', 'estado', 'total', 'created_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($p) => [
                'id'            => $p->id,
                'numero_pedido' => $p->numero_pedido,
                'estado'        => $p->estado,
                'total'         => $p->total,
                'created_at'    => $p->created_at,
            ]);

        return response()->json(['success' => true, 'pedidos' => $pedidos]);
    }

    /**
     * GET /tienda/cuenta/pedidos/{numero}
     */
    public function show(Request $request, string $numero): JsonResponse
    {
        $pedido = $request->user()
            ->pedidos()
            ->with([
                'detalles.producto.imagenPrincipal',
            ])
            ->where('numero_pedido', $numero)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'pedido'  => [
                'id'              => $pedido->id,
                'numero_pedido'   => $pedido->numero_pedido,
                'estado'          => $pedido->estado,
                'metodo_pago'     => $pedido->metodo_pago,
                'subtotal'        => $pedido->subtotal,
                'costo_envio'     => $pedido->costo_envio,
                'descuento_cupon' => $pedido->descuento_cupon,
                'total'           => $pedido->total,
                'puntos_ganados'  => $pedido->puntos_ganados,
                'nombre'          => $pedido->nombre,
                'telefono'        => $pedido->telefono,
                'departamento'    => $pedido->departamento,
                'municipio'       => $pedido->municipio,
                'direccion'       => $pedido->direccion,
                'referencias'     => $pedido->referencias,
                'notas'           => $pedido->notas,
                'comprobante_url' => $pedido->comprobante_url,
                'created_at'      => $pedido->created_at,
                'detalles'        => $pedido->detalles->map(fn($d) => [
                    'id'              => $d->id,
                    'producto_id'     => $d->producto_id,
                    'nombre_producto' => $d->nombre_producto,
                    'cantidad'        => $d->cantidad,
                    'precio_unitario' => $d->precio_unitario,
                    'subtotal'        => $d->subtotal,
                    'imagen'          => $d->producto && $d->producto->imagenPrincipal
                                      ? ($d->producto->imagenPrincipal->url_thumb ?? $d->producto->imagenPrincipal->url)
                                      : null,
                ]),
            ],
        ]);
    }
}