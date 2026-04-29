<?php

namespace App\Http\Controllers;

use App\Models\Credito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CreditoController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Credito::with(['pagos', 'venta']);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre_cliente',           'LIKE', "%{$search}%")
                      ->orWhere('producto_o_servicio_dado','LIKE', "%{$search}%");
                });
            }

            if ($request->filled('estado') && $request->estado !== 'todos') {
                $query->where('estado', $request->estado);
            }

            match ($request->get('sort', 'fecha_desc')) {
                'monto_desc' => $query->orderBy('capital', 'desc'),
                'monto_asc'  => $query->orderBy('capital', 'asc'),
                'fecha_asc'  => $query->orderBy('fecha_credito', 'asc'),
                default      => $query->orderBy('fecha_credito', 'desc'),
            };

            $creditos = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'success'       => true,
                'creditos'      => $creditos,
                'estadisticas'  => $this->obtenerEstadisticas(),
                'message'       => 'Filtrado exitoso'
            ]);

        } catch (\Exception $e) {
            Log::error('Error filtrando créditos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al filtrar créditos',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre_cliente'            => 'required|string|max:200',
                'capital'                   => 'required|numeric|min:0.01',
                'producto_o_servicio_dado'  => 'nullable|string',
                'fecha_credito'             => 'required|date',
                'capital_restante'          => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $capitalRestante = min($request->capital_restante, $request->capital);

            $credito = Credito::create([
                'nombre_cliente'           => $request->nombre_cliente,
                'capital'                  => $request->capital,
                'producto_o_servicio_dado' => $request->producto_o_servicio_dado,
                'fecha_credito'            => $request->fecha_credito,
                'capital_restante'         => $capitalRestante,
                'estado'                   => $capitalRestante === 0 ? 'pagado' : 'activo',
            ]);

            return response()->json([
                'success' => true,
                'credito' => $credito,
                'message' => 'Crédito creado exitosamente'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creando crédito: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear crédito',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $credito = Credito::with(['pagos', 'venta'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'credito' => $credito,
                'message' => 'Crédito obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Crédito no encontrado'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $credito = Credito::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'nombre_cliente'           => 'required|string|max:200',
                'capital'                  => 'required|numeric|min:0.01',
                'producto_o_servicio_dado' => 'nullable|string',
                'fecha_credito'            => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $credito->update([
                'nombre_cliente'           => $request->nombre_cliente,
                'capital'                  => $request->capital,
                'producto_o_servicio_dado' => $request->producto_o_servicio_dado,
                'fecha_credito'            => $request->fecha_credito,
            ]);

            return response()->json([
                'success' => true,
                'credito' => $credito->load(['pagos', 'venta']),
                'message' => 'Crédito actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error actualizando crédito: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar crédito',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $credito = Credito::findOrFail($id);
            $credito->pagos()->delete();
            $credito->delete();

            return response()->json(['success' => true, 'message' => 'Crédito eliminado exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al eliminar crédito'], 500);
        }
    }

    public function registrarPago(Request $request, $id)
    {
        try {
            $credito = Credito::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'monto'         => 'required|numeric|min:0.01',
                'tipo'          => 'required|in:abono,pago_total',
                'observaciones' => 'nullable|string',
                'fecha_pago'    => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            if ($request->monto > $credito->capital_restante && $request->tipo !== 'pago_total') {
                return response()->json([
                    'success'          => false,
                    'message'          => 'El monto no puede ser mayor al capital restante',
                    'capital_restante' => $credito->capital_restante
                ], 422);
            }

            $monto     = $request->tipo === 'pago_total' ? $credito->capital_restante : $request->monto;
            $fechaPago = $request->filled('fecha_pago') ? $request->fecha_pago : null;
            $pago      = $credito->registrarPago($monto, $request->tipo, $request->observaciones, $fechaPago);

            return response()->json([
                'success' => true,
                'credito' => $credito->load(['pagos', 'venta']),
                'pago'    => $pago,
                'message' => 'Pago registrado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error registrando pago: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar pago',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function changeStatus($id)
    {
        try {
            $credito = Credito::findOrFail($id);

            $estados      = ['activo', 'abonado', 'pagado'];
            $currentIndex = array_search($credito->estado, $estados);
            $nextIndex    = ($currentIndex + 1) % count($estados);

            $credito->estado = $estados[$nextIndex];
            $credito->save();

            if ($credito->estado === 'pagado' && $credito->venta_id) {
                \App\Models\Venta::where('id', $credito->venta_id)
                    ->where('estado', 'pendiente')
                    ->update(['estado' => 'completada']);
            }

            return response()->json([
                'success' => true,
                'credito' => $credito,
                'message' => 'Estado del crédito cambiado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al cambiar estado'], 500);
        }
    }

    public function search(Request $request)
    {
        try {
            $query  = $request->get('query', '');
            $estado = $request->get('estado', 'todos');

            $creditos = Credito::with(['pagos', 'venta'])
                ->when($query,  fn($q) => $q->where('nombre_cliente', 'LIKE', "%{$query}%")->orWhere('producto_o_servicio_dado', 'LIKE', "%{$query}%"))
                ->when($estado !== 'todos', fn($q) => $q->where('estado', $estado))
                ->orderBy('fecha_credito', 'desc')
                ->paginate(20);

            return response()->json(['success' => true, 'creditos' => $creditos, 'message' => 'Búsqueda completada']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error en la búsqueda'], 500);
        }
    }

    public function byEstado($estado)
    {
        try {
            if (!in_array($estado, ['activo', 'abonado', 'pagado'])) {
                return response()->json(['success' => false, 'message' => 'Estado no válido'], 422);
            }

            $creditos = Credito::with(['pagos', 'venta'])
                ->where('estado', $estado)
                ->orderBy('fecha_credito', 'desc')
                ->paginate(20);

            return response()->json(['success' => true, 'creditos' => $creditos]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener créditos'], 500);
        }
    }

    private function obtenerEstadisticas(): array
    {
        $totalActivos  = Credito::activos()->count();
        $totalAbonados = Credito::abonados()->count();
        $totalPagados  = Credito::pagados()->count();

        $totalCapitalActivo  = Credito::activos()->sum('capital_restante');
        $totalCapitalAbonado = Credito::abonados()->sum('capital_restante');

        $totalRecuperado = Credito::whereIn('estado', ['abonado', 'pagado'])
            ->sum(DB::raw('capital - capital_restante'));

        return [
            'total_creditos'           => $totalActivos + $totalAbonados + $totalPagados,
            'activos'                  => $totalActivos,
            'abonados'                 => $totalAbonados,
            'pagados'                  => $totalPagados,
            'capital_pendiente_activos'  => $totalCapitalActivo,
            'capital_pendiente_abonados' => $totalCapitalAbonado,
            'total_recuperado'         => $totalRecuperado,
        ];
    }
}
