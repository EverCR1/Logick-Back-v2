<?php

namespace App\Http\Controllers;

use App\Models\Cupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCuponController extends Controller
{
    /**
     * GET /admin/cupones
     * Lista paginada con filtros opcionales.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Cupon::query();

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($sub) use ($q) {
                $sub->where('codigo',      'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%");
            });
        }

        $countQuery = clone $query;
        $cupones    = $query->orderByDesc('created_at')->paginate($request->get('per_page', 20));
        $total      = $cupones->total();
        $activos    = (clone $countQuery)->where('estado', 'activo')->count();

        return response()->json([
            'success' => true,
            'cupones' => $cupones,
            'counts'  => [
                'total'    => $total,
                'activos'  => $activos,
                'inactivos' => $total - $activos,
            ],
        ]);
    }

    /**
     * POST /admin/cupones
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codigo'              => 'required|string|max:30|unique:cupones,codigo',
            'descripcion'         => 'nullable|string|max:255',
            'tipo'                => 'required|in:porcentaje,monto_fijo',
            'valor'               => 'required|numeric|min:0.01',
            'minimo_compra'       => 'nullable|numeric|min:0',
            'maximo_descuento'    => 'nullable|numeric|min:0',
            'usos_maximos'        => 'nullable|integer|min:1',
            'usos_por_cuenta'     => 'required|integer|min:1',
            'solo_primera_compra' => 'boolean',
            'es_publico'          => 'boolean',
            'fecha_inicio'        => 'nullable|date',
            'fecha_vencimiento'   => 'nullable|date|after_or_equal:fecha_inicio',
            'estado'              => 'required|in:activo,inactivo',
            'mensaje_error'       => 'nullable|string|max:255',
        ]);

        $data['codigo'] = strtoupper(trim($data['codigo']));

        $cupon = Cupon::create($data);

        return response()->json(['success' => true, 'cupon' => $cupon], 201);
    }

    /**
     * GET /admin/cupones/{id}
     */
    public function show(int $id): JsonResponse
    {
        $cupon = Cupon::findOrFail($id);

        return response()->json(['success' => true, 'cupon' => $cupon]);
    }

    /**
     * PUT /admin/cupones/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $cupon = Cupon::findOrFail($id);

        $data = $request->validate([
            'codigo'              => "required|string|max:30|unique:cupones,codigo,{$id}",
            'descripcion'         => 'nullable|string|max:255',
            'tipo'                => 'required|in:porcentaje,monto_fijo',
            'valor'               => 'required|numeric|min:0.01',
            'minimo_compra'       => 'nullable|numeric|min:0',
            'maximo_descuento'    => 'nullable|numeric|min:0',
            'usos_maximos'        => 'nullable|integer|min:1',
            'usos_por_cuenta'     => 'required|integer|min:1',
            'solo_primera_compra' => 'boolean',
            'es_publico'          => 'boolean',
            'fecha_inicio'        => 'nullable|date',
            'fecha_vencimiento'   => 'nullable|date|after_or_equal:fecha_inicio',
            'estado'              => 'required|in:activo,inactivo',
            'mensaje_error'       => 'nullable|string|max:255',
        ]);

        $data['codigo'] = strtoupper(trim($data['codigo']));

        $cupon->update($data);

        return response()->json(['success' => true, 'cupon' => $cupon]);
    }

    /**
     * DELETE /admin/cupones/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $cupon = Cupon::findOrFail($id);

        // No borrar si tiene pedidos asociados
        if ($cupon->pedidos()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar: el cupón ya fue usado en pedidos.',
            ], 422);
        }

        $cupon->delete();

        return response()->json(['success' => true, 'message' => 'Cupón eliminado.']);
    }

    /**
     * PATCH /admin/cupones/{id}/estado
     */
    public function toggleEstado(int $id): JsonResponse
    {
        $cupon = Cupon::findOrFail($id);

        $cupon->estado = $cupon->estado === 'activo' ? 'inactivo' : 'activo';
        $cupon->save();

        return response()->json([
            'success' => true,
            'estado'  => $cupon->estado,
            'message' => "Cupón marcado como {$cupon->estado}.",
        ]);
    }
}