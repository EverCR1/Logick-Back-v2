<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Producto;
use App\Models\Servicio;
use App\Models\Cliente;
use App\Models\Credito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VentaController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Venta::with(['detalles', 'cliente', 'usuario']);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('numero_venta', 'LIKE', "%{$search}%")
                      ->orWhereHas('cliente',  fn($q2) => $q2->where('nombre', 'LIKE', "%{$search}%")->orWhere('nit', 'LIKE', "%{$search}%"))
                      ->orWhereHas('detalles', fn($q2) => $q2->where('descripcion', 'LIKE', "%{$search}%"));
                });
            }

            if ($request->filled('estado') && $request->estado !== 'todos') {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('metodo_pago') && $request->metodo_pago !== 'todos') {
                $query->where('metodo_pago', $request->metodo_pago);
            }

            if ($request->filled('fecha_inicio')) {
                $query->whereDate('created_at', '>=', $request->fecha_inicio);
            }

            if ($request->filled('fecha_fin')) {
                $query->whereDate('created_at', '<=', $request->fecha_fin);
            }

            if ($request->filled('monto_min')) {
                $query->where('total', '>=', $request->monto_min);
            }

            if ($request->filled('monto_max')) {
                $query->where('total', '<=', $request->monto_max);
            }

            match ($request->get('sort', 'fecha_desc')) {
                'fecha_asc'  => $query->orderBy('created_at', 'asc'),
                'total_desc' => $query->orderBy('total', 'desc'),
                'total_asc'  => $query->orderBy('total', 'asc'),
                default      => $query->orderBy('created_at', 'desc'),
            };

            $ventas = $query->paginate($request->get('per_page', 20));

            $response = ['success' => true, 'ventas' => $ventas, 'message' => 'Filtrado exitoso'];

            if ($request->boolean('estadisticas')) {
                $response['estadisticas'] = $this->obtenerEstadisticas();
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Error filtrando ventas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al filtrar ventas',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'items'                    => 'required|array|min:1',
                'items.*.tipo'             => 'required|in:producto,servicio,otro',
                'items.*.cantidad'         => 'required|integer|min:1',
                'items.*.descripcion'      => 'required|string|max:500',
                'items.*.precio_unitario'  => 'required|numeric|min:0',
                'items.*.descuento'        => 'nullable|numeric|min:0',
                'items.*.producto_id'      => 'nullable|required_if:items.*.tipo,producto|exists:productos,id',
                'items.*.servicio_id'      => 'nullable|required_if:items.*.tipo,servicio|exists:servicios,id',
                'items.*.referencia'       => 'nullable|string|max:100',
                'cliente_id'               => 'nullable|exists:clientes,id',
                'sucursal_id'              => 'nullable|exists:sucursales,id',
                'metodo_pago'              => 'required|in:efectivo,tarjeta,transferencia,mixto,credito',
                'observaciones'            => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            foreach ($request->items as $index => $item) {
                if ($item['tipo'] === 'producto' && isset($item['producto_id'])) {
                    $producto = Producto::find($item['producto_id']);
                    if ($producto && $producto->stock < $item['cantidad']) {
                        return response()->json([
                            'success'          => false,
                            'message'          => "Stock insuficiente para {$producto->nombre}",
                            'producto'         => $producto->nombre,
                            'stock_disponible' => $producto->stock,
                            'stock_solicitado' => $item['cantidad'],
                            'item_index'       => $index
                        ], 422);
                    }
                }
            }

            DB::beginTransaction();

            $esCredito  = $request->metodo_pago === 'credito';
            $sucursalId = $request->sucursal_id ?? auth()->user()->sucursal_id;

            $venta = Venta::create([
                'numero_venta'    => Venta::generarNumeroVenta(),
                'cliente_id'      => $request->cliente_id,
                'usuario_id'      => auth()->id(),
                'sucursal_id'     => $sucursalId,
                'estado'          => $esCredito ? 'pendiente' : 'completada',
                'metodo_pago'     => $request->metodo_pago,
                'observaciones'   => $request->observaciones,
                'subtotal'        => 0,
                'descuento_total' => 0,
                'total'           => 0,
            ]);

            $subtotal       = 0;
            $descuentoTotal = 0;
            $total          = 0;

            foreach ($request->items as $item) {
                $detalleSubtotal  = $item['precio_unitario'] * $item['cantidad'];
                $detalleDescuento = $item['descuento'] ?? 0;
                $detalleTotal     = $detalleSubtotal - $detalleDescuento;

                VentaDetalle::create([
                    'venta_id'        => $venta->id,
                    'tipo'            => $item['tipo'],
                    'cantidad'        => $item['cantidad'],
                    'descripcion'     => $item['descripcion'],
                    'precio_unitario' => $item['precio_unitario'],
                    'descuento'       => $detalleDescuento,
                    'subtotal'        => $detalleSubtotal,
                    'total'           => $detalleTotal,
                    'producto_id'     => $item['producto_id'] ?? null,
                    'servicio_id'     => $item['servicio_id'] ?? null,
                    'referencia'      => $item['referencia']  ?? null,
                ]);

                $subtotal       += $detalleSubtotal;
                $descuentoTotal += $detalleDescuento;
                $total          += $detalleTotal;
            }

            $venta->update([
                'subtotal'        => $subtotal,
                'descuento_total' => $descuentoTotal,
                'total'           => $total
            ]);

            if ($esCredito) {
                $nombreCliente = $venta->cliente
                    ? $venta->cliente->nombre
                    : ($request->nombre_cliente_credito ?? 'Sin cliente');

                Credito::create([
                    'venta_id'                 => $venta->id,
                    'nombre_cliente'           => $nombreCliente,
                    'capital'                  => $total,
                    'capital_restante'         => $total,
                    'producto_o_servicio_dado' => $venta->numero_venta,
                    'fecha_credito'            => now()->toDateString(),
                    'estado'                   => 'activo',
                ]);
            }

            $venta->load(['detalles', 'cliente', 'usuario', 'credito']);

            DB::commit();

            return response()->json([
                'success' => true,
                'venta'   => $venta,
                'message' => 'Venta registrada exitosamente'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creando venta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear venta',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $venta = Venta::with(['detalles', 'cliente', 'usuario', 'sucursal'])
                ->with(['detalles.producto', 'detalles.servicio'])
                ->findOrFail($id);

            return response()->json(['success' => true, 'venta' => $venta, 'message' => 'Venta obtenida exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Venta no encontrada'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $venta = Venta::with('detalles')->findOrFail($id);

            if ($venta->estado === 'cancelada') {
                return response()->json(['success' => false, 'message' => 'No se puede editar una venta cancelada'], 422);
            }

            $validator = Validator::make($request->all(), [
                'cliente_id'             => 'nullable|exists:clientes,id',
                'sucursal_id'            => 'nullable|exists:sucursales,id',
                'estado'                 => 'nullable|in:pendiente,completada,cancelada',
                'metodo_pago'            => 'nullable|in:efectivo,tarjeta,transferencia,mixto,credito',
                'observaciones'          => 'nullable|string',
                'items'                  => 'sometimes|array|min:1',
                'items.*.id'             => 'nullable|exists:venta_detalles,id',
                'items.*.tipo'           => 'required_with:items|in:producto,servicio,otro',
                'items.*.cantidad'       => 'required_with:items|integer|min:1',
                'items.*.descripcion'    => 'required_with:items|string|max:500',
                'items.*.precio_unitario'=> 'required_with:items|numeric|min:0',
                'items.*.descuento'      => 'nullable|numeric|min:0',
                'items.*.producto_id'    => 'nullable|required_if:items.*.tipo,producto|exists:productos,id',
                'items.*.servicio_id'    => 'nullable|required_if:items.*.tipo,servicio|exists:servicios,id',
                'items.*.referencia'     => 'nullable|string|max:100',
                'items.*.eliminar'       => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $venta->update($request->only(['cliente_id', 'sucursal_id', 'estado', 'metodo_pago', 'observaciones']));

            if ($request->has('items')) {
                $subtotal       = 0;
                $descuentoTotal = 0;
                $total          = 0;

                $itemsIdsActuales  = $venta->detalles->pluck('id')->toArray();
                $itemsIdsRecibidos = [];

                foreach ($request->items as $itemData) {
                    if (isset($itemData['producto_id']) && $itemData['tipo'] === 'producto') {
                        $producto = Producto::find($itemData['producto_id']);

                        if (isset($itemData['id'])) {
                            $detalleExistente = VentaDetalle::find($itemData['id']);
                            if ($detalleExistente) {
                                $diferencia = $itemData['cantidad'] - $detalleExistente->cantidad;
                                if ($diferencia > 0 && $producto->stock < $diferencia) {
                                    throw new \Exception("Stock insuficiente para {$producto->nombre}");
                                }
                            }
                        } elseif ($producto->stock < $itemData['cantidad']) {
                            throw new \Exception("Stock insuficiente para {$producto->nombre}");
                        }
                    }

                    $detalleSubtotal  = $itemData['precio_unitario'] * $itemData['cantidad'];
                    $detalleDescuento = $itemData['descuento'] ?? 0;
                    $detalleTotal     = $detalleSubtotal - $detalleDescuento;

                    if (isset($itemData['id']) && !isset($itemData['eliminar'])) {
                        $detalle = VentaDetalle::find($itemData['id']);
                        $detalle->update([
                            'tipo'            => $itemData['tipo'],
                            'cantidad'        => $itemData['cantidad'],
                            'descripcion'     => $itemData['descripcion'],
                            'precio_unitario' => $itemData['precio_unitario'],
                            'descuento'       => $detalleDescuento,
                            'subtotal'        => $detalleSubtotal,
                            'total'           => $detalleTotal,
                            'producto_id'     => $itemData['producto_id'] ?? null,
                            'servicio_id'     => $itemData['servicio_id'] ?? null,
                            'referencia'      => $itemData['referencia']  ?? null,
                        ]);
                        $itemsIdsRecibidos[] = $itemData['id'];
                    } elseif (isset($itemData['eliminar']) && $itemData['eliminar'] && isset($itemData['id'])) {
                        VentaDetalle::find($itemData['id'])->delete();
                    } else {
                        VentaDetalle::create([
                            'venta_id'        => $venta->id,
                            'tipo'            => $itemData['tipo'],
                            'cantidad'        => $itemData['cantidad'],
                            'descripcion'     => $itemData['descripcion'],
                            'precio_unitario' => $itemData['precio_unitario'],
                            'descuento'       => $detalleDescuento,
                            'subtotal'        => $detalleSubtotal,
                            'total'           => $detalleTotal,
                            'producto_id'     => $itemData['producto_id'] ?? null,
                            'servicio_id'     => $itemData['servicio_id'] ?? null,
                            'referencia'      => $itemData['referencia']  ?? null,
                        ]);
                    }

                    if (!isset($itemData['eliminar']) || !$itemData['eliminar']) {
                        $subtotal       += $detalleSubtotal;
                        $descuentoTotal += $detalleDescuento;
                        $total          += $detalleTotal;
                    }
                }

                $itemsAEliminar = array_diff($itemsIdsActuales, $itemsIdsRecibidos);
                VentaDetalle::whereIn('id', $itemsAEliminar)->delete();

                $venta->update([
                    'subtotal'        => $subtotal,
                    'descuento_total' => $descuentoTotal,
                    'total'           => $total
                ]);
            }

            DB::commit();

            $venta->load(['detalles', 'cliente', 'usuario']);

            return response()->json(['success' => true, 'venta' => $venta, 'message' => 'Venta actualizada exitosamente']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error actualizando venta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar venta',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        return response()->json([
            'success' => false,
            'message' => 'No se puede eliminar ventas. Use cancelar en su lugar.'
        ], 422);
    }

    public function cancelar($id)
    {
        try {
            DB::beginTransaction();

            $venta = Venta::with(['detalles', 'credito.pagos'])->findOrFail($id);

            if ($venta->estado === 'cancelada') {
                return response()->json(['success' => false, 'message' => 'La venta ya está cancelada'], 422);
            }

            $debiaRevertirStock = in_array($venta->estado, ['completada', 'pendiente']);

            $venta->estado = 'cancelada';
            $venta->save();

            if ($debiaRevertirStock) {
                foreach ($venta->detalles as $detalle) {
                    if ($detalle->tipo === 'producto' && $detalle->producto) {
                        $detalle->producto->actualizarStock($detalle->cantidad, 'compra');
                    }
                }
            }

            if ($venta->credito) {
                $venta->credito->pagos()->delete();
                $venta->credito->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'venta'   => $venta->load('detalles'),
                'message' => 'Venta cancelada exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error cancelando venta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar venta',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function buscarProductos(Request $request)
    {
        try {
            $query = $request->get('query', '');
            $limit = $request->get('limit', 10);

            $productos = Producto::with('imagenes')
                ->where('estado', 'activo')
                ->where('stock', '>', 0)
                ->where(fn($q) => $q
                    ->where('nombre',      'LIKE', "%{$query}%")
                    ->orWhere('sku',       'LIKE', "%{$query}%")
                    ->orWhere('descripcion','LIKE',"%{$query}%")
                    ->orWhere('marca',     'LIKE', "%{$query}%"))
                ->select(['id', 'sku', 'nombre', 'precio_venta', 'precio_oferta', 'stock', 'marca'])
                ->limit($limit)
                ->get()
                ->map(fn($p) => [
                    'id'     => $p->id,
                    'sku'    => $p->sku,
                    'nombre' => $p->nombre,
                    'precio' => $p->precio_oferta ?? $p->precio_venta,
                    'stock'  => $p->stock,
                    'marca'  => $p->marca,
                    'tipo'   => 'producto',
                ]);

            return response()->json(['success' => true, 'productos' => $productos, 'message' => 'Productos encontrados']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al buscar productos'], 500);
        }
    }

    public function buscarServicios(Request $request)
    {
        try {
            $query = $request->get('query', '');
            $limit = $request->get('limit', 10);

            $servicios = Servicio::where('estado', 'activo')
                ->where(fn($q) => $q
                    ->where('nombre',     'LIKE', "%{$query}%")
                    ->orWhere('codigo',   'LIKE', "%{$query}%")
                    ->orWhere('descripcion','LIKE',"%{$query}%"))
                ->select(['id', 'codigo', 'nombre', 'precio_venta', 'precio_oferta'])
                ->limit($limit)
                ->get()
                ->map(fn($s) => [
                    'id'     => $s->id,
                    'codigo' => $s->codigo,
                    'nombre' => $s->nombre,
                    'precio' => $s->precio_oferta ?? $s->precio_venta,
                    'tipo'   => 'servicio',
                ]);

            return response()->json(['success' => true, 'servicios' => $servicios, 'message' => 'Servicios encontrados']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al buscar servicios'], 500);
        }
    }

    public function buscarClientes(Request $request)
    {
        try {
            $query = $request->get('query', '');
            $limit = $request->get('limit', 10);

            $clientes = Cliente::where('estado', 'activo')
                ->where(fn($q) => $q
                    ->where('nombre',   'LIKE', "%{$query}%")
                    ->orWhere('nit',    'LIKE', "%{$query}%")
                    ->orWhere('email',  'LIKE', "%{$query}%")
                    ->orWhere('telefono','LIKE',"%{$query}%"))
                ->select(['id', 'nombre', 'nit', 'email', 'telefono'])
                ->limit($limit)
                ->get();

            return response()->json(['success' => true, 'clientes' => $clientes, 'message' => 'Clientes encontrados']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al buscar clientes'], 500);
        }
    }

    public function search(Request $request)
    {
        try {
            $query       = $request->get('query', '');
            $estado      = $request->get('estado', 'todos');
            $fechaInicio = $request->get('fecha_inicio');
            $fechaFin    = $request->get('fecha_fin');

            $ventas = Venta::with(['detalles', 'cliente', 'usuario'])
                ->when($query,  fn($q) => $q
                    ->where('numero_venta', 'LIKE', "%{$query}%")
                    ->orWhereHas('cliente',  fn($q2) => $q2->where('nombre', 'LIKE', "%{$query}%"))
                    ->orWhereHas('detalles', fn($q2) => $q2->where('descripcion', 'LIKE', "%{$query}%")))
                ->when($estado !== 'todos', fn($q) => $q->where('estado', $estado))
                ->when($fechaInicio, fn($q) => $q->whereDate('created_at', '>=', $fechaInicio))
                ->when($fechaFin,    fn($q) => $q->whereDate('created_at', '<=', $fechaFin))
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json(['success' => true, 'ventas' => $ventas, 'message' => 'Búsqueda completada']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error en la búsqueda'], 500);
        }
    }

    public function reporte(Request $request)
    {
        try {
            $fechaInicio = $request->get('fecha_inicio', now()->startOfMonth()->format('Y-m-d'));
            $fechaFin    = $request->get('fecha_fin',    now()->format('Y-m-d'));

            $ventas = Venta::with(['detalles', 'cliente'])
                ->where('estado', 'completada')
                ->whereBetween('created_at', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59'])
                ->orderBy('created_at', 'asc')
                ->get();

            $totalVentas     = $ventas->count();
            $totalIngresos   = $ventas->sum('total');
            $totalDescuentos = $ventas->sum('descuento_total');

            $ventasPorDia = $ventas->groupBy(fn($v) => $v->created_at->format('Y-m-d'))
                ->map(fn($ventasDia) => [
                    'cantidad'   => $ventasDia->count(),
                    'total'      => $ventasDia->sum('total'),
                    'descuentos' => $ventasDia->sum('descuento_total'),
                ]);

            $ventasPorMetodo = $ventas->groupBy('metodo_pago')
                ->map(fn($v) => ['cantidad' => $v->count(), 'total' => $v->sum('total')]);

            $topProductos = VentaDetalle::whereHas('venta', fn($q) => $q
                    ->where('estado', 'completada')
                    ->whereBetween('created_at', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']))
                ->where('tipo', 'producto')->whereNotNull('producto_id')
                ->select('producto_id', DB::raw('SUM(cantidad) as cantidad'), DB::raw('SUM(total) as total'))
                ->with('producto:id,nombre')
                ->groupBy('producto_id')->orderBy('cantidad', 'desc')->limit(10)->get()
                ->map(fn($item) => ['producto' => $item->producto?->nombre ?? 'Desconocido', 'cantidad' => $item->cantidad, 'total' => $item->total]);

            $topServicios = VentaDetalle::whereHas('venta', fn($q) => $q
                    ->where('estado', 'completada')
                    ->whereBetween('created_at', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']))
                ->where('tipo', 'servicio')->whereNotNull('servicio_id')
                ->select('servicio_id', DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(total) as total'))
                ->with('servicio:id,nombre')
                ->groupBy('servicio_id')->orderBy('cantidad', 'desc')->limit(10)->get()
                ->map(fn($item) => ['servicio' => $item->servicio?->nombre ?? 'Desconocido', 'cantidad' => $item->cantidad, 'total' => $item->total]);

            return response()->json([
                'success' => true,
                'reporte' => [
                    'periodo'         => ['inicio' => $fechaInicio, 'fin' => $fechaFin],
                    'resumen'         => [
                        'total_ventas'    => $totalVentas,
                        'total_ingresos'  => $totalIngresos,
                        'total_descuentos'=> $totalDescuentos,
                        'promedio_venta'  => $totalVentas > 0 ? $totalIngresos / $totalVentas : 0,
                    ],
                    'por_dia'         => $ventasPorDia,
                    'por_metodo_pago' => $ventasPorMetodo,
                    'top_productos'   => $topProductos->values(),
                    'top_servicios'   => $topServicios->values(),
                    'ventas'          => $ventas,
                ],
                'message' => 'Reporte generado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error generando reporte: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al generar reporte'], 500);
        }
    }

    public function detalles($id)
    {
        try {
            $detalles = VentaDetalle::with(['producto', 'servicio'])->where('venta_id', $id)->get();

            return response()->json(['success' => true, 'detalles' => $detalles, 'message' => 'Detalles obtenidos exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener detalles'], 500);
        }
    }

    public function agregarDetalle(Request $request, $id)
    {
        try {
            $venta = Venta::findOrFail($id);

            if ($venta->estado === 'cancelada') {
                return response()->json(['success' => false, 'message' => 'No se puede agregar items a una venta cancelada'], 422);
            }

            $validator = Validator::make($request->all(), [
                'tipo'            => 'required|in:producto,servicio,otro',
                'cantidad'        => 'required|integer|min:1',
                'descripcion'     => 'required|string|max:500',
                'precio_unitario' => 'required|numeric|min:0',
                'descuento'       => 'nullable|numeric|min:0',
                'producto_id'     => 'nullable|required_if:tipo,producto|exists:productos,id',
                'servicio_id'     => 'nullable|required_if:tipo,servicio|exists:servicios,id',
                'referencia'      => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            if ($request->tipo === 'producto' && $request->producto_id) {
                $producto = Producto::find($request->producto_id);
                if ($producto && $producto->stock < $request->cantidad) {
                    return response()->json([
                        'success'          => false,
                        'message'          => 'Stock insuficiente',
                        'stock_disponible' => $producto->stock
                    ], 422);
                }
            }

            DB::beginTransaction();

            $subtotal = $request->precio_unitario * $request->cantidad;
            $descuento = $request->descuento ?? 0;
            $total    = $subtotal - $descuento;

            $detalle = VentaDetalle::create([
                'venta_id'        => $venta->id,
                'tipo'            => $request->tipo,
                'cantidad'        => $request->cantidad,
                'descripcion'     => $request->descripcion,
                'precio_unitario' => $request->precio_unitario,
                'descuento'       => $descuento,
                'subtotal'        => $subtotal,
                'total'           => $total,
                'producto_id'     => $request->producto_id,
                'servicio_id'     => $request->servicio_id,
                'referencia'      => $request->referencia,
            ]);

            $venta->subtotal        += $subtotal;
            $venta->descuento_total += $descuento;
            $venta->total           += $total;
            $venta->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'detalle' => $detalle->load(['producto', 'servicio']),
                'message' => 'Detalle agregado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error al agregar detalle'], 500);
        }
    }

    public function eliminarDetalle($id, $detalleId)
    {
        try {
            DB::beginTransaction();

            $venta = Venta::findOrFail($id);

            if ($venta->estado === 'cancelada') {
                return response()->json(['success' => false, 'message' => 'No se puede eliminar items de una venta cancelada'], 422);
            }

            $detalle = VentaDetalle::where('venta_id', $id)->where('id', $detalleId)->firstOrFail();

            $venta->subtotal        -= $detalle->subtotal;
            $venta->descuento_total -= $detalle->descuento;
            $venta->total           -= $detalle->total;
            $venta->save();

            $detalle->delete();

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Detalle eliminado exitosamente']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error al eliminar detalle'], 500);
        }
    }

    public function ultimos30Dias()
    {
        try {
            $fechaInicio = now()->subDays(30)->startOfDay();
            $fechaFin    = now()->endOfDay();

            $ventas = Venta::where('estado', 'completada')
                ->whereBetween('created_at', [$fechaInicio, $fechaFin])
                ->select(DB::raw('DATE(created_at) as fecha'), DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(total) as total'))
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('fecha', 'asc')
                ->get();

            $resultado = [];
            for ($i = 0; $i <= 30; $i++) {
                $fecha    = $fechaInicio->copy()->addDays($i);
                $fechaKey = $fecha->format('Y-m-d');
                $ventaDia = $ventas->firstWhere('fecha', $fechaKey);

                $resultado[] = [
                    'fecha'           => $fecha->format('Y-m-d'),
                    'fecha_formateada'=> $fecha->format('d/m'),
                    'cantidad'        => $ventaDia ? (int) $ventaDia->cantidad : 0,
                    'total'           => $ventaDia ? (float) $ventaDia->total  : 0,
                    'dia_semana'      => $fecha->locale('es')->dayName,
                    'es_fin_semana'   => $fecha->isWeekend(),
                    'es_hoy'          => $fecha->isToday(),
                ];
            }

            $estadisticas = [
                'total_periodo'   => ['cantidad' => $ventas->sum('cantidad'), 'total' => $ventas->sum('total')],
                'promedio_diario' => $ventas->count() > 0 ? $ventas->sum('total') / 30 : 0,
                'dia_mas_ventas'  => $ventas->sortByDesc('cantidad')->first()['fecha'] ?? null,
                'dia_mas_ingresos'=> $ventas->sortByDesc('total')->first()['fecha']    ?? null,
            ];

            return response()->json([
                'success' => true,
                'data'    => [
                    'periodo'       => ['inicio' => $fechaInicio->format('Y-m-d'), 'fin' => $fechaFin->format('Y-m-d'), 'dias' => 31],
                    'ventas_por_dia'=> $resultado,
                    'estadisticas'  => $estadisticas,
                ],
                'message' => 'Ventas de los últimos 30 días obtenidas exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener ventas'], 500);
        }
    }

    public function ventasPorRango(Request $request)
    {
        try {
            $request->validate([
                'fecha_inicio' => 'required|date',
                'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
                'agrupar_por'  => 'nullable|in:día,semana,mes',
            ]);

            $fechaInicio = \Carbon\Carbon::parse($request->fecha_inicio)->startOfDay();
            $fechaFin    = \Carbon\Carbon::parse($request->fecha_fin)->endOfDay();
            $agruparPor  = $request->get('agrupar_por', 'día');

            $query = Venta::where('estado', 'completada')->whereBetween('created_at', [$fechaInicio, $fechaFin]);

            $resultado = match ($agruparPor) {
                'semana' => $query->select(DB::raw('YEAR(created_at) as año'), DB::raw('WEEK(created_at) as semana'), DB::raw('MIN(DATE(created_at)) as fecha_inicio_semana'), DB::raw('MAX(DATE(created_at)) as fecha_fin_semana'), DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(total) as total'))
                    ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('WEEK(created_at)'))->orderBy('año')->orderBy('semana')->get()
                    ->map(fn($item) => ['periodo' => "Semana {$item->semana} - {$item->año}", 'fecha_inicio' => $item->fecha_inicio_semana, 'fecha_fin' => $item->fecha_fin_semana, 'cantidad' => (int) $item->cantidad, 'total' => (float) $item->total]),

                'mes' => $query->select(DB::raw('YEAR(created_at) as año'), DB::raw('MONTH(created_at) as mes'), DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(total) as total'))
                    ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))->orderBy('año')->orderBy('mes')->get()
                    ->map(fn($item) => ['periodo' => \Carbon\Carbon::createFromDate($item->año, $item->mes, 1)->locale('es')->isoFormat('MMMM YYYY'), 'mes' => $item->mes, 'año' => $item->año, 'cantidad' => (int) $item->cantidad, 'total' => (float) $item->total]),

                default => $query->select(DB::raw('DATE(created_at) as fecha'), DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(total) as total'))
                    ->groupBy(DB::raw('DATE(created_at)'))->orderBy('fecha')->get()
                    ->map(fn($item) => ['fecha' => $item->fecha, 'fecha_formateada' => \Carbon\Carbon::parse($item->fecha)->format('d/m/Y'), 'dia_semana' => \Carbon\Carbon::parse($item->fecha)->locale('es')->dayName, 'cantidad' => (int) $item->cantidad, 'total' => (float) $item->total]),
            };

            $estadisticas = [
                'total_ventas'   => $query->count(),
                'total_ingresos' => $query->sum('total'),
                'promedio_venta' => $query->avg('total') ?? 0,
            ];

            return response()->json([
                'success' => true,
                'data'    => [
                    'periodo'     => ['inicio' => $fechaInicio->format('Y-m-d'), 'fin' => $fechaFin->format('Y-m-d'), 'agrupado_por' => $agruparPor],
                    'ventas'      => $resultado,
                    'estadisticas'=> $estadisticas,
                ],
                'message' => 'Ventas por rango obtenidas exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener ventas por rango'], 500);
        }
    }

    private function obtenerEstadisticas(): array
    {
        $hoy    = Venta::completadas()->hoy();
        $semana = Venta::completadas()->estaSemana();
        $mes    = Venta::completadas()->esteMes();

        $metodosPago = Venta::completadas()
            ->select('metodo_pago', DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(total) as total'))
            ->groupBy('metodo_pago')
            ->get();

        $topProductos = VentaDetalle::whereHas('venta', fn($q) => $q->where('estado', 'completada'))
            ->where('tipo', 'producto')->whereNotNull('producto_id')
            ->select('producto_id', DB::raw('SUM(cantidad) as total_vendido'), DB::raw('SUM(total) as total_ingreso'))
            ->with('producto:id,nombre')
            ->groupBy('producto_id')->orderBy('total_vendido', 'desc')->limit(5)->get();

        $topServicios = VentaDetalle::whereHas('venta', fn($q) => $q->where('estado', 'completada'))
            ->where('tipo', 'servicio')->whereNotNull('servicio_id')
            ->select('servicio_id', DB::raw('COUNT(*) as total_vendido'), DB::raw('SUM(total) as total_ingreso'))
            ->with('servicio:id,nombre')
            ->groupBy('servicio_id')->orderBy('total_vendido', 'desc')->limit(5)->get();

        return [
            'totales'      => [
                'hoy'    => ['ventas' => $hoy->count(),    'total' => $hoy->sum('total')],
                'semana' => ['ventas' => $semana->count(), 'total' => $semana->sum('total')],
                'mes'    => ['ventas' => $mes->count(),    'total' => $mes->sum('total')],
            ],
            'metodos_pago' => $metodosPago,
            'top_productos'=> $topProductos,
            'top_servicios'=> $topServicios,
        ];
    }
}
