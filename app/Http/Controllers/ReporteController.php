<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReporteController extends Controller
{
    public function resumen()
    {
        try {
            $data = [
                'ventas'    => [
                    'hoy'            => Venta::whereDate('created_at', today())->sum('total'),
                    'semana'         => Venta::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('total'),
                    'mes'            => Venta::whereMonth('created_at', now()->month)->sum('total'),
                    'total'          => Venta::sum('total'),
                    'promedio_diario'=> Venta::avg('total') ?? 0,
                ],
                'clientes'  => [
                    'total'      => Cliente::count(),
                    'activos'    => Cliente::where('estado', 'activo')->count(),
                    'nuevos_mes' => Cliente::whereMonth('created_at', now()->month)->count(),
                    'con_ventas' => Cliente::has('ventas')->count(),
                ],
                'productos' => [
                    'total'             => Producto::count(),
                    'stock_bajo'        => Producto::whereColumn('stock', '<=', 'stock_minimo')->count(),
                    'agotados'          => Producto::where('stock', '<=', 0)->count(),
                    'valor_inventario'  => Producto::sum(DB::raw('stock * precio_compra')),
                ],
                'usuarios'  => [
                    'total'   => User::count(),
                    'activos' => User::where('estado', 'activo')->count(),
                ]
            ];

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('Error en resumen de reportes: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al cargar resumen'], 500);
        }
    }

    public function ventas(Request $request)
    {
        try {
            $fechaInicio = $request->get('fecha_inicio', now()->startOfMonth()->format('Y-m-d'));
            $fechaFin    = $request->get('fecha_fin',    now()->endOfMonth()->format('Y-m-d'));
            $clienteId   = $request->get('cliente_id');
            $vendedorId  = $request->get('vendedor_id');
            $metodoPago  = $request->get('metodo_pago');
            $estado      = $request->get('estado');

            $query = Venta::with(['cliente', 'vendedor'])
                ->whereBetween('created_at', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']);

            if ($clienteId)  $query->where('cliente_id',  $clienteId);
            if ($vendedorId) $query->where('usuario_id',  $vendedorId);
            if ($metodoPago) $query->where('metodo_pago', $metodoPago);
            if ($estado)     $query->where('estado',      $estado);

            $ventas = $query->orderBy('created_at', 'desc')->get();

            $resumen = [
                'total_ventas'    => $ventas->count(),
                'monto_total'     => $ventas->sum('total'),
                'promedio_venta'  => $ventas->avg('total'),
                'venta_maxima'    => $ventas->max('total'),
                'venta_minima'    => $ventas->min('total'),
                'por_metodo_pago' => $ventas->groupBy('metodo_pago')
                    ->map(fn($items) => ['cantidad' => $items->count(), 'total' => $items->sum('total')])
            ];

            return response()->json([
                'success' => true,
                'ventas'  => $ventas,
                'resumen' => $resumen,
                'filtros' => ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin]
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al generar reporte de ventas'], 500);
        }
    }

    public function productosMasVendidos(Request $request)
    {
        try {
            $fechaInicio = $request->get('fecha_inicio', now()->startOfMonth()->format('Y-m-d'));
            $fechaFin    = $request->get('fecha_fin',    now()->endOfMonth()->format('Y-m-d'));
            $limite      = $request->get('limite', 20);

            $productos = VentaDetalle::select('producto_id', DB::raw('COUNT(*) as veces_vendido'), DB::raw('SUM(cantidad) as total_unidades'), DB::raw('SUM(subtotal) as total_vendido'))
                ->with('producto')
                ->where('tipo', 'producto')->whereNotNull('producto_id')
                ->whereHas('venta', fn($q) => $q->whereBetween('created_at', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']))
                ->groupBy('producto_id')->orderByDesc('total_unidades')->limit($limite)->get();

            return response()->json(['success' => true, 'productos' => $productos, 'filtros' => ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin]]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al generar reporte de productos'], 500);
        }
    }

    public function inventario(Request $request)
    {
        try {
            $categoriaId = $request->get('categoria_id');
            $proveedorId = $request->get('proveedor_id');
            $estadoStock = $request->get('estado_stock', 'todos');

            $query = Producto::with(['categorias', 'proveedor']);

            if ($categoriaId) $query->whereHas('categorias', fn($q) => $q->where('categorias.id', $categoriaId));
            if ($proveedorId) $query->where('proveedor_id', $proveedorId);

            match ($estadoStock) {
                'bajo'   => $query->whereColumn('stock', '<=', 'stock_minimo')->where('stock', '>', 0),
                'agotado'=> $query->where('stock', '<=', 0),
                'normal' => $query->whereColumn('stock', '>', 'stock_minimo'),
                default  => null,
            };

            $productos = $query->orderBy('nombre')->get();

            $resumen = [
                'total_productos'         => $productos->count(),
                'valor_total_inventario'  => $productos->sum(fn($p) => $p->stock * $p->precio_compra),
                'valor_venta_total'       => $productos->sum(fn($p) => $p->stock * $p->precio_venta),
                'productos_bajo_stock'    => $productos->filter(fn($p) => $p->stock <= $p->stock_minimo && $p->stock > 0)->count(),
                'productos_agotados'      => $productos->filter(fn($p) => $p->stock <= 0)->count(),
            ];

            return response()->json(['success' => true, 'productos' => $productos, 'resumen' => $resumen]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al generar reporte de inventario'], 500);
        }
    }

    public function topClientes(Request $request)
    {
        try {
            $fechaInicio = $request->get('fecha_inicio', now()->startOfMonth());
            $fechaFin    = $request->get('fecha_fin',    now()->endOfMonth());
            $limite      = $request->get('limite', 10);

            $clientes = Cliente::withCount(['ventas' => fn($q) => $q->whereBetween('created_at', [$fechaInicio, $fechaFin])])
                ->withSum(['ventas as total_comprado' => fn($q) => $q->whereBetween('created_at', [$fechaInicio, $fechaFin])], 'total')
                ->where('estado', 'activo')
                ->having('ventas_count', '>', 0)
                ->orderByDesc('total_comprado')
                ->limit($limite)
                ->get();

            return response()->json(['success' => true, 'clientes' => $clientes, 'filtros' => ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin]]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al generar reporte de clientes'], 500);
        }
    }

    public function rendimientoVendedores(Request $request)
    {
        try {
            $fechaInicio = $request->get('fecha_inicio', now()->startOfMonth()->format('Y-m-d'));
            $fechaFin    = $request->get('fecha_fin',    now()->endOfMonth()->format('Y-m-d'));

            $vendedores = User::withCount(['ventas' => fn($q) => $q->whereBetween('created_at', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59'])])
                ->withSum(['ventas as total_ventas' => fn($q) => $q->whereBetween('created_at', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59'])], 'total')
                ->having('ventas_count', '>', 0)
                ->orderByDesc('total_ventas')
                ->get()
                ->map(fn($user) => [
                    'id'           => $user->id,
                    'nombres'      => $user->nombres,
                    'apellidos'    => $user->apellidos,
                    'email'        => $user->email,
                    'telefono'     => $user->telefono    ?? null,
                    'username'     => $user->username    ?? null,
                    'rol'          => $user->rol,
                    'estado'       => $user->estado,
                    'es_vendedor'  => $user->rol === 'vendedor',
                    'ventas_count' => $user->ventas_count,
                    'total_ventas' => $user->total_ventas ?? 0,
                ]);

            return response()->json([
                'success'    => true,
                'vendedores' => $vendedores,
                'filtros'    => ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin]
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al generar reporte de vendedores', 'error' => $e->getMessage()], 500);
        }
    }

    public function serviciosMasRealizados(Request $request)
    {
        try {
            $fechaInicio = $request->get('fecha_inicio', now()->startOfMonth()->format('Y-m-d'));
            $fechaFin    = $request->get('fecha_fin',    now()->endOfMonth()->format('Y-m-d'));
            $limite      = $request->get('limite', 20);

            $servicios = VentaDetalle::select('servicio_id', DB::raw('COUNT(*) as veces_realizado'), DB::raw('SUM(cantidad) as total_unidades'), DB::raw('SUM(subtotal) as total_facturado'))
                ->with('servicio')
                ->where('tipo', 'servicio')->whereNotNull('servicio_id')
                ->whereHas('venta', fn($q) => $q->whereBetween('created_at', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']))
                ->groupBy('servicio_id')->orderByDesc('total_unidades')->limit($limite)->get();

            return response()->json(['success' => true, 'servicios' => $servicios, 'filtros' => ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin]]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al generar reporte de servicios'], 500);
        }
    }

    public function sucursales(Request $request)
    {
        try {
            $fechaInicio = $request->get('fecha_inicio', now()->startOfMonth()->format('Y-m-d'));
            $fechaFin    = $request->get('fecha_fin',    now()->endOfMonth()->format('Y-m-d'));

            $sucursales = Sucursal::with('usuarios')
                ->withCount('usuarios')
                ->withCount([
                    'ventas as total_transacciones' => fn($q) => $q->whereBetween('created_at', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']),
                    'ventas as ventas_completadas'  => fn($q) => $q->where('estado', 'completada')->whereBetween('created_at', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']),
                    'ventas as ventas_pendientes'   => fn($q) => $q->where('estado', 'pendiente')->whereBetween('created_at',  [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']),
                    'ventas as ventas_canceladas'   => fn($q) => $q->where('estado', 'cancelada')->whereBetween('created_at',  [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']),
                ])
                ->withSum(['ventas as monto_total' => fn($q) => $q->where('estado', 'completada')->whereBetween('created_at', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59'])], 'total')
                ->get()
                ->map(fn($s) => [
                    'id'                  => $s->id,
                    'nombre'              => $s->nombre,
                    'direccion'           => $s->direccion,
                    'telefono'            => $s->telefono,
                    'estado'              => $s->estado,
                    'usuarios_count'      => $s->usuarios_count,
                    'total_transacciones' => $s->total_transacciones ?? 0,
                    'ventas_completadas'  => $s->ventas_completadas  ?? 0,
                    'ventas_pendientes'   => $s->ventas_pendientes   ?? 0,
                    'ventas_canceladas'   => $s->ventas_canceladas   ?? 0,
                    'monto_total'         => (float) ($s->monto_total ?? 0),
                    'promedio_venta'      => $s->ventas_completadas > 0 ? round($s->monto_total / $s->ventas_completadas, 2) : 0,
                ])
                ->sortByDesc('monto_total')
                ->values();

            $resumen = [
                'total_sucursales'    => $sucursales->count(),
                'sucursales_activas'  => $sucursales->where('estado', 'activo')->count(),
                'monto_total_global'  => $sucursales->sum('monto_total'),
                'transacciones_total' => $sucursales->sum('total_transacciones'),
                'mejor_sucursal'      => $sucursales->first()['nombre'] ?? '—',
            ];

            return response()->json([
                'success'    => true,
                'sucursales' => $sucursales,
                'resumen'    => $resumen,
                'filtros'    => ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin],
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al generar reporte de sucursales'], 500);
        }
    }

    public function ganancias(Request $request)
    {
        try {
            $fechaInicio = $request->get('fecha_inicio', now()->startOfMonth()->format('Y-m-d'));
            $fechaFin    = $request->get('fecha_fin',    now()->endOfMonth()->format('Y-m-d'));
            $sucursalId  = $request->get('sucursal_id');
            $vendedorId  = $request->get('vendedor_id');
            $tipo        = $request->get('tipo');

            $query = VentaDetalle::with(['producto', 'servicio', 'venta.sucursal', 'venta.vendedor'])
                ->whereHas('venta', function ($q) use ($fechaInicio, $fechaFin, $sucursalId, $vendedorId) {
                    $q->where('estado', 'completada')
                      ->whereBetween('created_at', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']);
                    if ($sucursalId) $q->where('sucursal_id', $sucursalId);
                    if ($vendedorId) $q->where('usuario_id',  $vendedorId);
                });

            if ($tipo) $query->where('tipo', $tipo);

            $detalles    = $query->get();
            $items       = [];
            $porSucursal = [];
            $porVendedor = [];

            foreach ($detalles as $det) {
                $ingresos = (float) $det->total;
                $cantidad = (int)   $det->cantidad;

                if ($det->tipo === 'producto' && $det->producto) {
                    $costoUnitario = (float) ($det->producto->precio_compra      ?? 0);
                    $nombre        = $det->producto->nombre;
                    $tieneCosto    = $det->producto->precio_compra !== null;
                } elseif ($det->tipo === 'servicio' && $det->servicio) {
                    $costoUnitario = (float) ($det->servicio->inversion_estimada ?? 0);
                    $nombre        = $det->servicio->nombre;
                    $tieneCosto    = $det->servicio->inversion_estimada !== null;
                } else {
                    $costoUnitario = 0;
                    $nombre        = $det->descripcion ?? 'Item personalizado';
                    $tieneCosto    = false;
                }

                $costoLinea    = $costoUnitario * $cantidad;
                $gananciaLinea = $ingresos - $costoLinea;

                $itemKey = ($det->tipo ?? 'otro') . '_' . ($det->producto_id ?? $det->servicio_id ?? 'custom');
                if (!isset($items[$itemKey])) {
                    $items[$itemKey] = ['nombre' => $nombre, 'tipo' => $det->tipo ?? 'otro', 'unidades' => 0, 'ingresos' => 0.0, 'costo_total' => 0.0, 'ganancia' => 0.0, 'tiene_costo' => $tieneCosto];
                }
                $items[$itemKey]['unidades']    += $cantidad;
                $items[$itemKey]['ingresos']    += $ingresos;
                $items[$itemKey]['costo_total'] += $costoLinea;
                $items[$itemKey]['ganancia']    += $gananciaLinea;

                $sucNombre = $det->venta->sucursal->nombre ?? 'Sin sucursal';
                $sucKey    = 'suc_' . ($det->venta->sucursal_id ?? 'none');
                if (!isset($porSucursal[$sucKey])) {
                    $porSucursal[$sucKey] = ['nombre' => $sucNombre, 'ingresos' => 0.0, 'costos' => 0.0, 'ganancia' => 0.0];
                }
                $porSucursal[$sucKey]['ingresos'] += $ingresos;
                $porSucursal[$sucKey]['costos']   += $costoLinea;
                $porSucursal[$sucKey]['ganancia'] += $gananciaLinea;

                $vendNombre = trim(($det->venta->vendedor->nombres ?? '') . ' ' . ($det->venta->vendedor->apellidos ?? '')) ?: 'Sin vendedor';
                $vendKey    = 'vend_' . ($det->venta->usuario_id ?? 'none');
                if (!isset($porVendedor[$vendKey])) {
                    $porVendedor[$vendKey] = ['nombre' => $vendNombre, 'ingresos' => 0.0, 'costos' => 0.0, 'ganancia' => 0.0];
                }
                $porVendedor[$vendKey]['ingresos'] += $ingresos;
                $porVendedor[$vendKey]['costos']   += $costoLinea;
                $porVendedor[$vendKey]['ganancia'] += $gananciaLinea;
            }

            $items = collect($items)->map(function ($item) {
                $item['margen']      = $item['ingresos'] > 0 ? round($item['ganancia'] / $item['ingresos'] * 100, 1) : 0;
                $item['ingresos']    = round($item['ingresos'],    2);
                $item['costo_total'] = round($item['costo_total'], 2);
                $item['ganancia']    = round($item['ganancia'],    2);
                return $item;
            })->sortByDesc('ganancia')->values();

            $porSucursal = collect($porSucursal)->map(function ($s) {
                $s['margen']   = $s['ingresos'] > 0 ? round($s['ganancia'] / $s['ingresos'] * 100, 1) : 0;
                $s['ingresos'] = round($s['ingresos'], 2);
                $s['costos']   = round($s['costos'],   2);
                $s['ganancia'] = round($s['ganancia'], 2);
                return $s;
            })->sortByDesc('ganancia')->values();

            $porVendedor = collect($porVendedor)->map(function ($v) {
                $v['margen']   = $v['ingresos'] > 0 ? round($v['ganancia'] / $v['ingresos'] * 100, 1) : 0;
                $v['ingresos'] = round($v['ingresos'], 2);
                $v['costos']   = round($v['costos'],   2);
                $v['ganancia'] = round($v['ganancia'], 2);
                return $v;
            })->sortByDesc('ganancia')->values();

            $totalIngresos = $items->sum('ingresos');
            $totalCostos   = $items->sum('costo_total');
            $totalGanancia = $items->sum('ganancia');

            $productosItems = $items->where('tipo', 'producto');
            $serviciosItems = $items->where('tipo', 'servicio');

            $resumen = [
                'ingresos_totales'  => round($totalIngresos, 2),
                'costos_totales'    => round($totalCostos,   2),
                'ganancia_neta'     => round($totalGanancia, 2),
                'margen_porcentaje' => $totalIngresos > 0 ? round($totalGanancia / $totalIngresos * 100, 1) : 0,
                'items_vendidos'    => (int) $items->sum('unidades'),
            ];

            $porTipo = [
                'productos' => ['ingresos' => round($productosItems->sum('ingresos'), 2), 'costos' => round($productosItems->sum('costo_total'), 2), 'ganancia' => round($productosItems->sum('ganancia'), 2), 'unidades' => (int) $productosItems->sum('unidades')],
                'servicios' => ['ingresos' => round($serviciosItems->sum('ingresos'), 2), 'costos' => round($serviciosItems->sum('costo_total'), 2), 'ganancia' => round($serviciosItems->sum('ganancia'), 2), 'unidades' => (int) $serviciosItems->sum('unidades')],
            ];

            $catalogos = [
                'sucursales' => Sucursal::where('estado', 'activo')->select('id', 'nombre')->orderBy('nombre')->get(),
                'vendedores' => User::whereIn('rol', ['administrador', 'vendedor'])->where('estado', 'activo')->select('id', 'nombres', 'apellidos')->orderBy('nombres')->get()
                    ->map(fn($u) => ['id' => $u->id, 'nombre' => trim($u->nombres . ' ' . $u->apellidos)]),
            ];

            return response()->json([
                'success'      => true,
                'resumen'      => $resumen,
                'por_tipo'     => $porTipo,
                'items'        => $items,
                'por_sucursal' => $porSucursal,
                'por_vendedor' => $porVendedor,
                'catalogos'    => $catalogos,
                'filtros'      => ['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin, 'sucursal_id' => $sucursalId, 'vendedor_id' => $vendedorId, 'tipo' => $tipo],
            ]);

        } catch (\Exception $e) {
            Log::error('Error en reporte ganancias: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al generar reporte de ganancias'], 500);
        }
    }

    public function exportar(Request $request)
    {
        // Implementar exportación PDF/Excel según necesidad
    }
}
