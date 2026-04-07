<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Auditoria::with('usuario')->latest();

            if ($request->filled('modulo') && $request->modulo !== 'todos') {
                $query->where('modulo', $request->modulo);
            }

            if ($request->filled('accion') && $request->accion !== 'todos') {
                $query->where('accion', $request->accion);
            }

            if ($request->filled('usuario_id') && $request->usuario_id !== 'todos') {
                $query->where('usuario_id', $request->usuario_id);
            }

            if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
                $query->whereBetween('created_at', [
                    $request->fecha_inicio . ' 00:00:00',
                    $request->fecha_fin    . ' 23:59:59'
                ]);
            }

            if ($request->filled('busqueda')) {
                $query->buscar($request->busqueda);
            }

            $auditoria = $query->paginate($request->get('per_page', 50));

            return response()->json([
                'success'   => true,
                'auditoria' => $auditoria,
                'message'   => 'Logs de auditoría obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo auditoría: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener logs de auditoría',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $auditoria = Auditoria::with('usuario')->findOrFail($id);

            return response()->json([
                'success'   => true,
                'auditoria' => $auditoria,
                'message'   => 'Log obtenido exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Log no encontrado',
                'error'   => $e->getMessage()
            ], 404);
        }
    }

    public function estadisticas(Request $request)
    {
        try {
            $dias        = $request->get('dias', 30);
            $fechaInicio = now()->subDays($dias);

            $accionesPorDia = Auditoria::where('created_at', '>=', $fechaInicio)
                ->selectRaw('DATE(created_at) as fecha, COUNT(*) as total')
                ->groupBy('fecha')
                ->orderBy('fecha')
                ->get();

            $accionesPorTipo = Auditoria::where('created_at', '>=', $fechaInicio)
                ->selectRaw('accion, COUNT(*) as total')
                ->groupBy('accion')
                ->get();

            $accionesPorModulo = Auditoria::where('created_at', '>=', $fechaInicio)
                ->selectRaw('modulo, accion, COUNT(*) as total')
                ->groupBy('modulo', 'accion')
                ->orderBy('modulo')
                ->get();

            $modulosAgrupados = [];
            foreach ($accionesPorModulo as $item) {
                $modulo = $item['modulo'];
                $accion = $item['accion'];
                $total  = $item['total'];

                if (!isset($modulosAgrupados[$modulo])) {
                    $modulosAgrupados[$modulo] = [
                        'modulo'         => $modulo,
                        'creaciones'     => 0,
                        'ediciones'      => 0,
                        'eliminaciones'  => 0,
                        'cambios_estado' => 0,
                        'total'          => 0
                    ];
                }

                match ($accion) {
                    'CREAR'        => $modulosAgrupados[$modulo]['creaciones']     = $total,
                    'EDITAR'       => $modulosAgrupados[$modulo]['ediciones']      = $total,
                    'ELIMINAR'     => $modulosAgrupados[$modulo]['eliminaciones']  = $total,
                    'CAMBIO_ESTADO'=> $modulosAgrupados[$modulo]['cambios_estado'] = $total,
                    default        => null,
                };
                $modulosAgrupados[$modulo]['total'] += $total;
            }

            $modulosOrdenados = collect($modulosAgrupados)->sortByDesc('total')->values()->toArray();

            $usuariosActivos = Auditoria::where('created_at', '>=', $fechaInicio)
                ->selectRaw('usuario_id, usuario_nombre, usuario_rol, COUNT(*) as total')
                ->groupBy('usuario_id', 'usuario_nombre', 'usuario_rol')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success'      => true,
                'estadisticas' => [
                    'total_acciones'      => Auditoria::where('created_at', '>=', $fechaInicio)->count(),
                    'acciones_por_dia'    => $accionesPorDia,
                    'acciones_por_tipo'   => $accionesPorTipo,
                    'acciones_por_modulo' => $modulosOrdenados,
                    'usuarios_activos'    => $usuariosActivos,
                    'fecha_inicio'        => $fechaInicio->format('Y-m-d'),
                    'fecha_fin'           => now()->format('Y-m-d')
                ],
                'message' => 'Estadísticas obtenidas exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo estadísticas de auditoría: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function modulos()
    {
        try {
            $modulos = Auditoria::select('modulo')->distinct()->orderBy('modulo')->pluck('modulo');

            return response()->json(['success' => true, 'modulos' => $modulos]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener módulos',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
