<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Models\CuentaDireccion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CuentaDireccionController extends Controller
{
    /**
     * GET /tienda/cuenta/direcciones
     */
    public function index(Request $request): JsonResponse
    {
        $direcciones = $request->user()
            ->direcciones()
            ->orderByDesc('es_principal')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($d) => $this->formatear($d));

        return response()->json(['success' => true, 'direcciones' => $direcciones]);
    }

    /**
     * POST /tienda/cuenta/direcciones
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'alias'           => 'nullable|string|max:60',
            'nombre_receptor' => 'required|string|max:100',
            'telefono'        => 'required|string|max:20',
            'departamento'    => 'required|string|max:80',
            'municipio'       => 'required|string|max:80',
            'direccion'       => 'required|string|max:255',
            'referencias'     => 'nullable|string|max:255',
        ]);

        $cuenta = $request->user();

        // Si es la primera dirección, marcarla como principal automáticamente
        $esPrimera = $cuenta->direcciones()->count() === 0;

        $direccion = $cuenta->direcciones()->create([
            ...$data,
            'cuenta_id'    => $cuenta->id,
            'es_principal' => $esPrimera,
        ]);

        return response()->json([
            'success'   => true,
            'direccion' => $this->formatear($direccion),
        ], 201);
    }

    /**
     * PUT /tienda/cuenta/direcciones/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $direccion = $this->encontrar($request, $id);

        $data = $request->validate([
            'alias'           => 'nullable|string|max:60',
            'nombre_receptor' => 'sometimes|required|string|max:100',
            'telefono'        => 'sometimes|required|string|max:20',
            'departamento'    => 'sometimes|required|string|max:80',
            'municipio'       => 'sometimes|required|string|max:80',
            'direccion'       => 'sometimes|required|string|max:255',
            'referencias'     => 'nullable|string|max:255',
        ]);

        $direccion->update($data);

        return response()->json([
            'success'   => true,
            'direccion' => $this->formatear($direccion->fresh()),
        ]);
    }

    /**
     * DELETE /tienda/cuenta/direcciones/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $direccion    = $this->encontrar($request, $id);
        $eraPrincipal = $direccion->es_principal;
        $cuenta       = $request->user();

        $direccion->delete();

        // Si era la principal, asignar la siguiente más reciente
        if ($eraPrincipal) {
            $siguiente = $cuenta->direcciones()->orderByDesc('created_at')->first();
            if ($siguiente) {
                $siguiente->update(['es_principal' => true]);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * PUT /tienda/cuenta/direcciones/{id}/principal
     */
    public function marcarPrincipal(Request $request, int $id): JsonResponse
    {
        $cuenta    = $request->user();
        $direccion = $this->encontrar($request, $id);

        DB::transaction(function () use ($cuenta, $direccion) {
            $cuenta->direcciones()->update(['es_principal' => false]);
            $direccion->update(['es_principal' => true]);
        });

        return response()->json(['success' => true]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function encontrar(Request $request, int $id): CuentaDireccion
    {
        return $request->user()->direcciones()->findOrFail($id);
    }

    private function formatear(CuentaDireccion $d): array
    {
        return [
            'id'              => $d->id,
            'alias'           => $d->alias,
            'nombre_receptor' => $d->nombre_receptor,
            'telefono'        => $d->telefono,
            'departamento'    => $d->departamento,
            'municipio'       => $d->municipio,
            'direccion'       => $d->direccion,
            'referencias'     => $d->referencias,
            'es_principal'    => $d->es_principal,
        ];
    }
}