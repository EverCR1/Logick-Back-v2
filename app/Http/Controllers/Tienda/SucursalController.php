<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use Illuminate\Http\JsonResponse;

class SucursalController extends Controller
{
    /**
     * GET /tienda/sucursales
     * Lista pública de sucursales activas con los datos necesarios para el checkout.
     */
    public function index(): JsonResponse
    {
        $sucursales = Sucursal::where('estado', 'activo')
            ->orderBy('nombre')
            ->get()
            ->map(fn($s) => [
                'id'           => $s->id,
                'nombre'       => $s->nombre,
                'direccion'    => $s->direccion,
                'municipio'    => $s->municipio,
                'departamento' => $s->departamento,
                'referencia'   => $s->referencia,
                'horario'      => $s->horario,
                'telefono'     => $s->telefono,
                'lat'          => $s->lat ? (float) $s->lat : null,
                'lng'          => $s->lng ? (float) $s->lng : null,
            ]);

        return response()->json([
            'success'    => true,
            'sucursales' => $sucursales,
        ]);
    }
}