<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombres'   => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'email'     => 'required|string|email|max:100|unique:users',
            'username'  => 'required|string|max:50|unique:users',
            'password'  => [
                'required', 'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols()
            ],
            'rol'       => 'required|in:administrador,vendedor,analista',
            'telefono'  => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'nombres'   => $request->nombres,
            'apellidos' => $request->apellidos,
            'email'     => $request->email,
            'username'  => $request->username,
            'password'  => Hash::make($request->password),
            'rol'       => $request->rol,
            'telefono'  => $request->telefono,
            'direccion' => $request->direccion,
            'estado'    => 'activo',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'Usuario registrado exitosamente',
            'user'         => $user,
            'access_token' => $token,
            'token_type'   => 'Bearer'
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required_without:username|email|exists:users,email',
            'username' => 'required_without:email|string|exists:users,username',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors'  => $validator->errors()
            ], 422);
        }

        $credentials = [];
        if ($request->email) {
            $credentials['email'] = $request->email;
        } else {
            $credentials['username'] = $request->username;
        }
        $credentials['password'] = $request->password;

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        $user = User::where('email', $request->email)
                    ->orWhere('username', $request->username)
                    ->first();

        if ($user->estado !== 'activo') {
            return response()->json([
                'message' => 'Tu cuenta está inactiva. Contacta al administrador.'
            ], 403);
        }

        $user->tokens()->delete();

        DB::table('users')
            ->where('id', $user->id)
            ->update(['last_login_at' => now()]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Inicio de sesión exitoso',
            'user'    => [
                'id'              => $user->id,
                'nombres'         => $user->nombres,
                'apellidos'       => $user->apellidos,
                'nombre_completo' => $user->nombre_completo,
                'email'           => $user->email,
                'username'        => $user->username,
                'rol'             => $user->rol,
                'estado'          => $user->estado,
                'telefono'        => $user->telefono,
                'sucursal_id'     => $user->sucursal_id,
                'last_login_at'   => $user->last_login_at,
            ],
            'access_token' => $token,
            'token_type'   => 'Bearer'
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada exitosamente']);
    }

    public function profile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id'              => $user->id,
                'nombres'         => $user->nombres,
                'apellidos'       => $user->apellidos,
                'nombre_completo' => $user->nombre_completo,
                'email'           => $user->email,
                'username'        => $user->username,
                'rol'             => $user->rol,
                'estado'          => $user->estado,
                'telefono'        => $user->telefono,
                'direccion'       => $user->direccion,
                'created_at'      => $user->created_at,
                'updated_at'      => $user->updated_at,
            ]
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'nombres'   => 'sometimes|string|max:100',
            'apellidos' => 'sometimes|string|max:100',
            'email'     => 'sometimes|string|email|max:100|unique:users,email,' . $user->id,
            'username'  => 'sometimes|string|max:50|unique:users,username,' . $user->id,
            'telefono'  => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user->update($request->only(['nombres', 'apellidos', 'email', 'username', 'telefono', 'direccion']));

        return response()->json([
            'message' => 'Perfil actualizado exitosamente',
            'user'    => $user
        ]);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password'     => [
                'required', 'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols()
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'La contraseña actual es incorrecta'], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Contraseña cambiada exitosamente. Inicia sesión nuevamente.'
        ]);
    }
}
