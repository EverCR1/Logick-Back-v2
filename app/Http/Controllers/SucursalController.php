<?php

namespace App\Http\Controllers;

use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SucursalController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Sucursal::withCount('usuarios', 'ventas');

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre',   'LIKE', "%{$search}%")
                      ->orWhere('direccion', 'LIKE', "%{$search}%")
                      ->orWhere('telefono',  'LIKE', "%{$search}%");
                });
            }

            if ($request->filled('estado') && $request->estado !== 'todos') {
                $query->where('estado', $request->estado);
            }

            $sucursales = $query->orderBy('nombre')->paginate($request->get('per_page', 20));

            return response()->json(['success' => true, 'sucursales' => $sucursales]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener sucursales',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre'    => 'required|string|max:150|unique:sucursales,nombre',
            'direccion' => 'nullable|string|max:255',
            'telefono'  => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $sucursal = Sucursal::create([
            'nombre'    => $request->nombre,
            'direccion' => $request->direccion,
            'telefono'  => $request->telefono,
            'estado'    => 'activo',
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'Sucursal creada exitosamente',
            'sucursal' => $sucursal,
        ], 201);
    }

    public function show($id)
    {
        $sucursal = Sucursal::withCount('ventas')
            ->with(['usuarios' => function ($q) {
                $q->select('id', 'sucursal_id', 'nombres', 'apellidos', 'rol', 'estado');
            }])
            ->find($id);

        if (!$sucursal) {
            return response()->json(['message' => 'Sucursal no encontrada'], 404);
        }

        return response()->json(['success' => true, 'sucursal' => $sucursal]);
    }

    public function update(Request $request, $id)
    {
        $sucursal = Sucursal::find($id);

        if (!$sucursal) {
            return response()->json(['message' => 'Sucursal no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre'    => 'sometimes|string|max:150|unique:sucursales,nombre,' . $id,
            'direccion' => 'nullable|string|max:255',
            'telefono'  => 'nullable|string|max:20',
            'estado'    => 'sometimes|in:activo,inactivo',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $sucursal->update($request->only(['nombre', 'direccion', 'telefono', 'estado']));

        return response()->json([
            'success'  => true,
            'message'  => 'Sucursal actualizada exitosamente',
            'sucursal' => $sucursal,
        ]);
    }

    public function destroy($id)
    {
        $sucursal = Sucursal::find($id);

        if (!$sucursal) {
            return response()->json(['message' => 'Sucursal no encontrada'], 404);
        }

        if ($sucursal->usuarios()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar: hay usuarios asignados a esta sucursal',
            ], 422);
        }

        $sucursal->delete();

        return response()->json(['success' => true, 'message' => 'Sucursal eliminada exitosamente']);
    }

    public function changeStatus(Request $request, $id)
    {
        $sucursal = Sucursal::find($id);

        if (!$sucursal) {
            return response()->json(['message' => 'Sucursal no encontrada'], 404);
        }

        $nuevoEstado = $request->has('estado')
            ? $request->estado
            : ($sucursal->estado === 'activo' ? 'inactivo' : 'activo');

        $sucursal->update(['estado' => $nuevoEstado]);

        $mensaje = $nuevoEstado === 'activo'
            ? 'Sucursal activada exitosamente'
            : 'Sucursal desactivada exitosamente';

        return response()->json(['success' => true, 'message' => $mensaje, 'sucursal' => $sucursal]);
    }

    public function activas()
    {
        $sucursales = Sucursal::where('estado', 'activo')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'direccion', 'telefono']);

        return response()->json(['success' => true, 'sucursales' => $sucursales]);
    }
}
