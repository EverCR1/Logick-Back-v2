<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Models\ReporteProblema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public const CATEGORIAS = [
        'pedido'      => 'Problema con mi pedido',
        'pago'        => 'Problema con el pago',
        'producto'    => 'Producto incorrecto o dañado',
        'cuenta'      => 'Problema con mi cuenta',
        'envio'       => 'Problema con el envío',
        'tienda'      => 'Error en la tienda en línea',
        'otro'        => 'Otro',
    ];

    private function resolverCuenta(): ?Cuenta
    {
        $user = auth('sanctum')->user();
        return ($user instanceof Cuenta) ? $user : null;
    }

    /**
     * GET /tienda/reportes/categorias
     */
    public function categorias(): JsonResponse
    {
        return response()->json(['success' => true, 'categorias' => self::CATEGORIAS]);
    }

    /**
     * POST /tienda/reportes
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categoria'          => 'required|string|in:' . implode(',', array_keys(self::CATEGORIAS)),
            'descripcion'        => 'required|string|min:20|max:1000',
            'nombre_contacto'    => 'nullable|string|max:120',
            'email_contacto'     => 'nullable|email|max:150',
            'telefono_contacto'  => 'nullable|string|max:30',
        ]);

        $cuenta = $this->resolverCuenta();
        $ip     = $request->ip();

        // Anti-spam: máximo 2 reportes activos
        if ($cuenta) {
            $activos = ReporteProblema::where('cuenta_id', $cuenta->id)
                ->whereIn('estado', ['pendiente', 'en_revision'])
                ->count();
        } else {
            $activos = ReporteProblema::where('ip_address', $ip)
                ->whereNull('cuenta_id')
                ->whereIn('estado', ['pendiente', 'en_revision'])
                ->count();
        }

        if ($activos >= 2) {
            return response()->json([
                'success' => false,
                'message' => 'Ya tienes 2 reportes activos. Espera a que sean resueltos antes de enviar uno nuevo.',
            ], 422);
        }

        ReporteProblema::create([
            'cuenta_id'          => $cuenta?->id,
            'categoria'          => $data['categoria'],
            'descripcion'        => $data['descripcion'],
            'nombre_contacto'    => $data['nombre_contacto']   ?? ($cuenta ? trim($cuenta->nombre . ' ' . $cuenta->apellido) : null),
            'email_contacto'     => $data['email_contacto']    ?? $cuenta?->email,
            'telefono_contacto'  => $data['telefono_contacto'] ?? $cuenta?->telefono,
            'estado'             => 'pendiente',
            'ip_address'         => $ip,
        ]);

        return response()->json([
            'success' => true,
            'message' => '¡Gracias por tu reporte! Lo revisaremos pronto. Si aplica, recibirás puntos o un cupón como agradecimiento.',
        ], 201);
    }

    /**
     * GET /tienda/cuenta/reportes  (auth.cuenta)
     */
    public function miReportes(Request $request): JsonResponse
    {
        $reportes = ReporteProblema::where('cuenta_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn($r) => [
                'id'               => $r->id,
                'categoria'        => self::CATEGORIAS[$r->categoria] ?? $r->categoria,
                'descripcion'      => $r->descripcion,
                'estado'           => $r->estado,
                'puntos_otorgados' => $r->puntos_otorgados,
                'nota_admin'       => $r->nota_admin,
                'created_at'       => $r->created_at->format('d/m/Y'),
            ]);

        return response()->json(['success' => true, 'reportes' => $reportes]);
    }
}
