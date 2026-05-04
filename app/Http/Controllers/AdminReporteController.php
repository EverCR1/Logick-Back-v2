<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Tienda\ReporteController;
use App\Models\CuentaPunto;
use App\Models\ReporteProblema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReporteController extends Controller
{
    /**
     * GET /admin/reportes
     */
    public function index(Request $request): JsonResponse
    {
        $query      = ReporteProblema::with('cuenta:id,nombre,apellido,email')->orderBy('created_at', 'desc');
        $countQuery = ReporteProblema::query();

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
            $countQuery->where('categoria', $request->categoria);
        }

        $reportes = $query->paginate($request->get('per_page', 20));

        $reportes->getCollection()->transform(fn($r) => array_merge($r->toArray(), [
            'categoria_label' => ReporteController::CATEGORIAS[$r->categoria] ?? $r->categoria,
        ]));

        $byEstado = $countQuery->selectRaw('estado, COUNT(*) as cnt')->groupBy('estado')->pluck('cnt', 'estado');

        $pend = (int) ($byEstado['pendiente']   ?? 0);
        $rev  = (int) ($byEstado['en_revision'] ?? 0);
        $res  = (int) ($byEstado['resuelto']    ?? 0);
        $inv  = (int) ($byEstado['invalido']    ?? 0);

        return response()->json([
            'success'  => true,
            'reportes' => $reportes,
            'counts'   => [
                'total'       => $pend + $rev + $res + $inv,
                'pendiente'   => $pend,
                'en_revision' => $rev,
                'resuelto'    => $res,
                'invalido'    => $inv,
            ],
        ]);
    }

    /**
     * GET /admin/reportes/{id}
     */
    public function show(int $id): JsonResponse
    {
        $reporte = ReporteProblema::with('cuenta:id,nombre,apellido,email,puntos_saldo')->find($id);

        if (!$reporte) {
            return response()->json(['success' => false, 'message' => 'Reporte no encontrado'], 404);
        }

        return response()->json([
            'success' => true,
            'reporte' => array_merge($reporte->toArray(), [
                'categoria_label' => ReporteController::CATEGORIAS[$reporte->categoria] ?? $reporte->categoria,
            ]),
        ]);
    }

    /**
     * PATCH /admin/reportes/{id}
     * Actualiza estado, nota_admin y opcionalmente otorga puntos.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'estado'          => 'required|in:pendiente,en_revision,resuelto,invalido',
            'nota_admin'      => 'nullable|string|max:1000',
            'puntos_otorgados'=> 'nullable|integer|min:1|max:9999',
        ]);

        $reporte = ReporteProblema::find($id);

        if (!$reporte) {
            return response()->json(['success' => false, 'message' => 'Reporte no encontrado'], 404);
        }

        DB::transaction(function () use ($reporte, $data) {
            $puntos         = $data['puntos_otorgados'] ?? null;
            $estadoNuevo    = $data['estado'];
            $resueltoAhora  = $estadoNuevo === 'resuelto' && $reporte->estado !== 'resuelto';

            $reporte->update([
                'estado'           => $estadoNuevo,
                'nota_admin'       => $data['nota_admin'] ?? $reporte->nota_admin,
                'puntos_otorgados' => $puntos ?? $reporte->puntos_otorgados,
            ]);

            // Otorgar puntos solo cuando se marca como resuelto por primera vez y hay cuenta
            if ($resueltoAhora && $puntos && $reporte->cuenta_id) {
                $yaTieneMovimiento = CuentaPunto::where('referencia_type', 'reporte')
                    ->where('referencia_id', $reporte->id)
                    ->where('tipo', 'ajuste')
                    ->exists();

                if (!$yaTieneMovimiento) {
                    CuentaPunto::create([
                        'cuenta_id'       => $reporte->cuenta_id,
                        'tipo'            => 'ajuste',
                        'puntos'          => $puntos,
                        'referencia_id'   => $reporte->id,
                        'referencia_type' => 'reporte',
                        'concepto'        => "Reporte #{$reporte->id} resuelto — {$puntos} puntos",
                    ]);

                    \App\Models\Cuenta::where('id', $reporte->cuenta_id)->increment('puntos_saldo', $puntos);
                }
            }
        });

        return response()->json(['success' => true, 'message' => 'Reporte actualizado.']);
    }
}
