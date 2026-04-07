<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Cliente::query();

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre',   'LIKE', "%{$search}%")
                      ->orWhere('nit',     'LIKE', "%{$search}%")
                      ->orWhere('email',   'LIKE', "%{$search}%")
                      ->orWhere('telefono','LIKE', "%{$search}%")
                      ->orWhere('notas',   'LIKE', "%{$search}%");
                });
            }

            if ($request->filled('estado') && $request->estado !== 'todos') {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('tipo') && $request->tipo !== 'todos') {
                $query->where('tipo', $request->tipo);
            }

            $clientes = $query->orderBy('nombre')->paginate($request->get('per_page', 20));

            return response()->json(['success' => true, 'clientes' => $clientes, 'message' => 'Filtrado exitoso']);

        } catch (\Exception $e) {
            Log::error('Error filtrando clientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al filtrar clientes',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function todos(Request $request)
    {
        try {
            $query = Cliente::query();

            if ($request->filled('query')) {
                $searchTerm = $request->query;
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('nombre',   'like', "%{$searchTerm}%")
                      ->orWhere('nit',     'like', "%{$searchTerm}%")
                      ->orWhere('email',   'like', "%{$searchTerm}%")
                      ->orWhere('telefono','like', "%{$searchTerm}%")
                      ->orWhere('notas',   'like', "%{$searchTerm}%");
                });
            }

            if ($request->filled('estado') && $request->estado !== 'todos') {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('tipo') && $request->tipo !== 'todos') {
                $query->where('tipo', $request->tipo);
            }

            $clientes = $query->orderBy('nombre')->get();

            return response()->json([
                'success'  => true,
                'clientes' => $clientes->toArray(),
                'total'    => $clientes->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error en API clientes/todos: ' . $e->getMessage());
            return response()->json([
                'success'  => false,
                'message'  => 'Error al obtener clientes',
                'clientes' => []
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre'    => 'required|string|max:200',
                'nit'       => 'nullable|string|max:20|unique:clientes,nit',
                'email'     => 'nullable|email|max:100|unique:clientes,email',
                'telefono'  => 'nullable|string|max:20',
                'direccion' => 'nullable|string|max:500',
                'tipo'      => 'required|in:natural,juridico',
                'estado'    => 'nullable|in:activo,inactivo',
                'notas'     => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $cliente = Cliente::create([
                'nombre'    => $request->nombre,
                'nit'       => $request->nit,
                'email'     => $request->email,
                'telefono'  => $request->telefono,
                'direccion' => $request->direccion,
                'tipo'      => $request->tipo,
                'estado'    => $request->estado ?? 'activo',
                'notas'     => $request->notas,
            ]);

            return response()->json([
                'success' => true,
                'cliente' => $cliente,
                'message' => 'Cliente creado exitosamente'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creando cliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear cliente',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $cliente = Cliente::with(['ventas' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(10);
            }])->findOrFail($id);

            $estadisticas = [
                'total_ventas'      => $cliente->ventas()->completadas()->count(),
                'total_gastado'     => $cliente->ventas()->completadas()->sum('total'),
                'ultima_compra'     => $cliente->ventas()->completadas()->latest()->first(),
                'ventas_mes_actual' => $cliente->ventas()->completadas()->whereMonth('created_at', now()->month)->count(),
                'total_mes_actual'  => $cliente->ventas()->completadas()->whereMonth('created_at', now()->month)->sum('total'),
            ];

            return response()->json([
                'success'       => true,
                'cliente'       => $cliente,
                'estadisticas'  => $estadisticas,
                'message'       => 'Cliente obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo cliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado',
                'error'   => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $cliente = Cliente::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'nombre'    => 'required|string|max:200',
                'nit'       => 'nullable|string|max:20|unique:clientes,nit,' . $id,
                'email'     => 'nullable|email|max:100|unique:clientes,email,' . $id,
                'telefono'  => 'nullable|string|max:20',
                'direccion' => 'nullable|string|max:500',
                'tipo'      => 'required|in:natural,juridico',
                'estado'    => 'required|in:activo,inactivo',
                'notas'     => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $cliente->update([
                'nombre'    => $request->nombre,
                'nit'       => $request->nit,
                'email'     => $request->email,
                'telefono'  => $request->telefono,
                'direccion' => $request->direccion,
                'tipo'      => $request->tipo,
                'estado'    => $request->estado,
                'notas'     => $request->notas,
            ]);

            return response()->json([
                'success' => true,
                'cliente' => $cliente,
                'message' => 'Cliente actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error actualizando cliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar cliente',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $cliente = Cliente::findOrFail($id);

            if ($cliente->ventas()->exists()) {
                $cliente->update(['estado' => 'inactivo']);
                return response()->json([
                    'success' => true,
                    'message' => 'Cliente marcado como inactivo (tiene ventas asociadas)',
                    'cliente' => $cliente
                ]);
            }

            $cliente->delete();

            return response()->json(['success' => true, 'message' => 'Cliente eliminado exitosamente']);

        } catch (\Exception $e) {
            Log::error('Error eliminando cliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar cliente',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function changeStatus($id)
    {
        try {
            $cliente = Cliente::findOrFail($id);
            $cliente->estado = $cliente->estado === 'activo' ? 'inactivo' : 'activo';
            $cliente->save();

            return response()->json([
                'success' => true,
                'cliente' => $cliente,
                'message' => 'Estado del cliente cambiado exitosamente'
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
            $tipo   = $request->get('tipo', 'todos');

            $clientes = Cliente::when($query, fn($q) => $q
                    ->where('nombre',   'LIKE', "%{$query}%")
                    ->orWhere('nit',    'LIKE', "%{$query}%")
                    ->orWhere('email',  'LIKE', "%{$query}%")
                    ->orWhere('telefono','LIKE',"%{$query}%"))
                ->when($estado !== 'todos', fn($q) => $q->where('estado', $estado))
                ->when($tipo   !== 'todos', fn($q) => $q->where('tipo',   $tipo))
                ->orderBy('nombre')
                ->paginate(20);

            return response()->json(['success' => true, 'clientes' => $clientes, 'message' => 'Búsqueda completada']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error en la búsqueda'], 500);
        }
    }

    public function frecuentes()
    {
        try {
            $clientes = Cliente::withCount(['ventas' => fn($q) => $q->completadas()])
                ->withSum(['ventas as total_gastado' => fn($q) => $q->completadas()], 'total')
                ->where('estado', 'activo')
                ->having('ventas_count', '>', 0)
                ->orderBy('ventas_count', 'desc')
                ->limit(10)
                ->get();

            return response()->json(['success' => true, 'clientes' => $clientes, 'message' => 'Clientes frecuentes obtenidos exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener clientes frecuentes'], 500);
        }
    }

    public function estadisticas()
    {
        try {
            $totalClientes    = Cliente::count();
            $clientesActivos  = Cliente::where('estado', 'activo')->count();
            $conVentas        = Cliente::has('ventas')->count();

            return response()->json([
                'success'       => true,
                'estadisticas'  => [
                    'total_clientes'   => $totalClientes,
                    'activos'          => $clientesActivos,
                    'inactivos'        => $totalClientes - $clientesActivos,
                    'naturales'        => Cliente::where('tipo', 'natural')->count(),
                    'juridicos'        => Cliente::where('tipo', 'juridico')->count(),
                    'con_ventas'       => $conVentas,
                    'sin_ventas'       => $totalClientes - $conVentas,
                    'porcentaje_activos' => $totalClientes > 0 ? ($clientesActivos / $totalClientes) * 100 : 0,
                ],
                'message' => 'Estadísticas obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener estadísticas'], 500);
        }
    }

    public function crearRapido(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre'   => 'required|string|max:200',
                'nit'      => 'nullable|string|max:20',
                'telefono' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $cliente = Cliente::create([
                'nombre'   => $request->nombre,
                'nit'      => $request->nit,
                'telefono' => $request->telefono,
                'tipo'     => 'natural',
                'estado'   => 'activo',
            ]);

            return response()->json([
                'success' => true,
                'cliente' => $cliente,
                'message' => 'Cliente creado exitosamente para venta'
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al crear cliente'], 500);
        }
    }
}
