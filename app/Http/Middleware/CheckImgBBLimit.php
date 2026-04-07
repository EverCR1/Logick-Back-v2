<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ImagenProducto;

class CheckImgBBLimit
{
    public function handle(Request $request, Closure $next)
    {
        $todayCount = ImagenProducto::whereDate('created_at', today())
            ->where('disk', 'imgbb')
            ->count();

        $dailyLimit = 15;

        if ($todayCount >= $dailyLimit) {
            return response()->json([
                'message' => 'Límite diario de imágenes alcanzado',
                'limit'   => $dailyLimit,
                'used'    => $todayCount
            ], 429);
        }

        return $next($request);
    }
}
