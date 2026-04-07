<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query  = $request->get('query', '');
            $estado = $request->get('estado', 'todos');

            $usersQuery = User::query();

            if (!empty($query)) {
                $usersQuery->where(function ($q) use ($query) {
                    $q->where('nombres',   'like', "%{$query}%")
                      ->orWhere('apellidos','like', "%{$query}%")
                      ->orWhere('username', 'like', "%{$query}%")
                      ->orWhere('email',    'like', "%{$query}%");
                });
            }

            if ($estado !== 'todos') {
                $usersQuery->where('estado', $estado);
            }

            $users = $usersQuery->orderBy('nombres')->paginate(20);

            return response()->json([
                'success' => true,
                'users'   => $users,
                'message' => 'Usuarios obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo usuarios: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuarios',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombres'     => 'required|string|max:100',
            'apellidos'   => 'required|string|max:100',
            'email'       => 'required|string|email|max:100|unique:users',
            'username'    => 'required|string|max:50|unique:users',
            'password'    => ['required', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'rol'         => 'required|in:administrador,vendedor,analista',
            'estado'      => 'required|in:activo,inactivo',
            'telefono'    => 'nullable|string|max:20',
            'direccion'   => 'nullable|string|max:255',
            'sucursal_id' => 'nullable|exists:sucursales,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'nombres'     => $request->nombres,
            'apellidos'   => $request->apellidos,
            'email'       => $request->email,
            'username'    => $request->username,
            'password'    => Hash::make($request->password),
            'rol'         => $request->rol,
            'estado'      => $request->estado,
            'telefono'    => $request->telefono,
            'direccion'   => $request->direccion,
            'sucursal_id' => $request->sucursal_id,
        ]);

        return response()->json([
            'message' => 'Usuario creado exitosamente',
            'user'    => $user
        ], 201);
    }

    public function show(string $id)
    {
        $user = User::with([
            'sucursal',
            'ventas' => fn($q) => $q->latest()->limit(5),
        ])->find($id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $totalVentas       = $user->ventas()->count();
        $montoTotal        = $user->ventas()->where('estado', 'completada')->sum('total');
        $ventasCompletadas = $user->ventas()->where('estado', 'completada')->count();
        $ventasPendientes  = $user->ventas()->where('estado', 'pendiente')->count();

        return response()->json([
            'user'   => $user,
            'ventas' => [
                'total'       => $totalVentas,
                'monto_total' => $montoTotal,
                'completadas' => $ventasCompletadas,
                'pendientes'  => $ventasPendientes,
                'recientes'   => $user->ventas,
            ],
        ]);
    }

    public function update(Request $request, string $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombres'     => 'sometimes|string|max:100',
            'apellidos'   => 'sometimes|string|max:100',
            'email'       => 'sometimes|string|email|max:100|unique:users,email,' . $id,
            'username'    => 'sometimes|string|max:50|unique:users,username,' . $id,
            'rol'         => 'sometimes|in:administrador,vendedor,analista',
            'estado'      => 'sometimes|in:activo,inactivo',
            'telefono'    => 'nullable|string|max:20',
            'direccion'   => 'nullable|string|max:255',
            'sucursal_id' => 'nullable|exists:sucursales,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user->update($request->only([
            'nombres', 'apellidos', 'email', 'username',
            'rol', 'estado', 'telefono', 'direccion', 'sucursal_id'
        ]));

        if ($request->filled('password')) {
            $pwValidator = Validator::make($request->all(), [
                'password' => [Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            ]);
            if (!$pwValidator->fails()) {
                $user->update(['password' => Hash::make($request->password)]);
            }
        }

        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'user'    => $user
        ]);
    }

    public function destroy(string $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        if (auth()->id() == $id) {
            return response()->json(['message' => 'No puedes eliminar tu propio usuario'], 403);
        }

        $totalVentas = $user->ventas()->count();

        if ($totalVentas > 0) {
            return response()->json([
                'message' => "No se puede eliminar el usuario porque tiene {$totalVentas} venta(s) registrada(s) en el sistema."
            ], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Usuario eliminado exitosamente']);
    }

    public function changeStatus(Request $request, string $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'estado' => 'required|in:activo,inactivo',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user->update(['estado' => $request->estado]);

        return response()->json([
            'message' => 'Estado actualizado exitosamente',
            'user'    => $user
        ]);
    }
}
