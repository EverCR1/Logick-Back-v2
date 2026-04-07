<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!Auth::check()) {
            return response()->json([
                'message' => 'No autenticado'
            ], 401);
        }

        $user = Auth::user();

        if (!in_array($user->rol, $roles)) {
            return response()->json([
                'message' => 'No tienes permisos para acceder a esta ruta'
            ], 403);
        }

        return $next($request);
    }
}
