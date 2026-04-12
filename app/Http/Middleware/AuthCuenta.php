<?php

namespace App\Http\Middleware;

use App\Models\Cuenta;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthCuenta
{
    /**
     * Verifica que el token Sanctum pertenezca a una Cuenta (no a un User del dashboard).
     * Usar en rutas protegidas de la tienda.
     */
    public function handle(Request $request, Closure $next)
    {
        Auth::shouldUse('sanctum');

        $usuario = auth('sanctum')->user();

        if (!$usuario || !($usuario instanceof Cuenta)) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado. Inicia sesión para continuar.',
            ], 401);
        }

        if ($usuario->estado !== 'activo') {
            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta está suspendida.',
            ], 403);
        }

        return $next($request);
    }
}