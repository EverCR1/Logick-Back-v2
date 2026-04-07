<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Proveedor::query();

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre',      'LIKE', "%{$search}%")
                      ->orWhere('email',     'LIKE', "%{$search}%")
                      ->orWhere('telefono',  'LIKE', "%{$search}%")
                      ->orWhere('direccion', 'LIKE', "%{$search}%")
                      ->orWhere('descripcion','LIKE',"%{$search}%");
                });
            }

            if ($request->filled('estado') && $request->estado !== 'todos') {
                $query->where('estado', $request->estado);
            }

            $proveedores = $query->orderBy('nombre')->paginate($request->get('per_page', 20));

            return response()->json([
                'success'     => true,
                'proveedores' => $proveedores,
                'message'     => 'Filtrado exitoso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al filtrar proveedores',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre'      => 'required|string|max:200',
            'email'       => 'nullable|email|max:100',
            'telefono'    => 'nullable|string|max:20',
            'direccion'   => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors'  => $validator->errors()
            ], 422);
        }

        $proveedor = Proveedor::create([
            'nombre'      => $request->nombre,
            'email'       => $request->email,
            'telefono'    => $request->telefono,
            'direccion'   => $request->direccion,
            'descripcion' => $request->descripcion,
            'estado'      => 'activo',
        ]);

        return response()->json([
            'message'    => 'Proveedor creado exitosamente',
            'proveedor'  => $proveedor
        ], 201);
    }

    public function show(string $id)
    {
        $proveedor = Proveedor::withCount([
            'productos',
            'productos as productos_activos_count' => fn($q) => $q->where('estado', 'activo'),
        ])
        ->with([
            'productos' => fn($q) => $q
                ->select('id', 'proveedor_id', 'sku', 'nombre', 'marca', 'precio_venta', 'stock', 'stock_minimo', 'estado')
                ->orderBy('nombre')
                ->limit(50),
        ])
        ->find($id);

        if (!$proveedor) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        return response()->json([
            'proveedor' => array_merge($proveedor->toArray(), [
                'dias_como_proveedor' => (int) $proveedor->created_at->diffInDays(now()),
            ])
        ]);
    }

    public function update(Request $request, string $id)
    {
        $proveedor = Proveedor::find($id);

        if (!$proveedor) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre'      => 'sometimes|string|max:200',
            'email'       => 'nullable|email|max:100',
            'telefono'    => 'nullable|string|max:20',
            'direccion'   => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'estado'      => 'sometimes|in:activo,inactivo',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors'  => $validator->errors()
            ], 422);
        }

        $proveedor->update($request->all());

        return response()->json([
            'message'   => 'Proveedor actualizado exitosamente',
            'proveedor' => $proveedor
        ]);
    }

    public function destroy(string $id)
    {
        $proveedor = Proveedor::find($id);

        if (!$proveedor) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $proveedor->delete();

        return response()->json(['message' => 'Proveedor eliminado exitosamente']);
    }

    public function changeStatus(Request $request, string $id)
    {
        $proveedor = Proveedor::find($id);

        if (!$proveedor) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $nuevoEstado = $request->has('estado')
            ? $request->estado
            : ($proveedor->estado === 'activo' ? 'inactivo' : 'activo');

        $proveedor->update(['estado' => $nuevoEstado]);

        $mensaje = $nuevoEstado === 'activo'
            ? 'Proveedor activado exitosamente'
            : 'Proveedor desactivado exitosamente';

        return response()->json(['message' => $mensaje, 'proveedor' => $proveedor]);
    }

    public function activos()
    {
        return response()->json([
            'proveedores' => Proveedor::where('estado', 'activo')->get()
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->get('q', '');

        $proveedores = Proveedor::where('nombre',   'LIKE', "%{$query}%")
            ->orWhere('email',    'LIKE', "%{$query}%")
            ->orWhere('telefono', 'LIKE', "%{$query}%")
            ->get();

        return response()->json(['proveedores' => $proveedores]);
    }
}
