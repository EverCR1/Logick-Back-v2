<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class CuentaAuthController extends Controller
{
    /**
     * Registro de nueva cuenta.
     * POST /tienda/auth/registro
     */
    public function registro(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre'            => 'required|string|max:80',
            'apellido'          => 'required|string|max:80',
            'email'             => 'required|email|unique:cuentas,email',
            'password'          => ['required', 'confirmed', Password::min(8)],
            'telefono'          => 'nullable|string|max:20',
        ], [
            'email.unique'      => 'Ya existe una cuenta con ese correo.',
            'password.confirmed'=> 'Las contraseñas no coinciden.',
        ]);

        $cuenta = Cuenta::create([
            'nombre'   => $data['nombre'],
            'apellido' => $data['apellido'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'telefono' => $data['telefono'] ?? null,
            'estado'   => 'activo',
        ]);

        $token = $cuenta->createToken('tienda')->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'cuenta'  => $this->formatearCuenta($cuenta),
        ], 201);
    }

    /**
     * Login de cuenta existente.
     * POST /tienda/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $cuenta = Cuenta::where('email', $data['email'])->first();

        // No revelar si el email existe o no
        if (!$cuenta || !$cuenta->password || !Hash::check($data['password'], $cuenta->password)) {
            throw ValidationException::withMessages([
                'email' => 'Credenciales incorrectas.',
            ]);
        }

        if ($cuenta->estado !== 'activo') {
            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta está suspendida. Contáctanos para más información.',
            ], 403);
        }

        // Revocar tokens anteriores de la misma sesión (opcional: permitir multi-dispositivo)
        // $cuenta->tokens()->delete();

        $token = $cuenta->createToken('tienda')->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'cuenta'  => $this->formatearCuenta($cuenta),
        ]);
    }

    /**
     * Cerrar sesión (invalida el token actual).
     * POST /tienda/auth/logout  — requiere auth
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Sesión cerrada.']);
    }

    /**
     * Datos de la cuenta autenticada.
     * GET /tienda/auth/me  — requiere auth
     */
    public function me(Request $request): JsonResponse
    {
        $cuenta = $request->user()->load('direcciones');

        return response()->json([
            'success' => true,
            'cuenta'  => $this->formatearCuenta($cuenta, true),
        ]);
    }

    /**
     * Cambiar contraseña.
     * POST /tienda/auth/cambiar-password  — requiere auth
     */
    public function cambiarPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password_actual' => 'required|string',
            'password'        => ['required', 'confirmed', Password::min(8)],
        ]);

        $cuenta = $request->user();

        if (!Hash::check($data['password_actual'], $cuenta->password)) {
            throw ValidationException::withMessages([
                'password_actual' => 'La contraseña actual es incorrecta.',
            ]);
        }

        $cuenta->update(['password' => Hash::make($data['password'])]);

        return response()->json(['success' => true, 'message' => 'Contraseña actualizada.']);
    }

    /**
     * Actualizar datos del perfil.
     * PUT /tienda/auth/perfil  — requiere auth
     */
    public function actualizarPerfil(Request $request): JsonResponse
    {
        $cuenta = $request->user();

        $data = $request->validate([
            'nombre'   => 'sometimes|required|string|max:80',
            'apellido' => 'sometimes|required|string|max:80',
            'telefono' => 'nullable|string|max:20',
            'email'    => 'sometimes|required|email|unique:cuentas,email,' . $cuenta->id,
        ], [
            'email.unique' => 'Ese correo ya está en uso por otra cuenta.',
        ]);

        $cuenta->update($data);

        return response()->json([
            'success' => true,
            'cuenta'  => $this->formatearCuenta($cuenta->fresh()),
        ]);
    }

    // ── Google OAuth ───────────────────────────────────────────────────────────

    /**
     * Redirige a Google para autenticación.
     * GET /tienda/auth/google
     */
    public function googleRedirect()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Callback de Google. Crea o recupera la cuenta y redirige al frontend con token.
     * GET /tienda/auth/google/callback
     */
    public function googleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Google OAuth error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
            return redirect("{$frontendUrl}/auth/google/callback?error=oauth_failed");
        }

        // Buscar por google_id primero, luego por email
        $cuenta = Cuenta::where('google_id', $googleUser->getId())->first()
               ?? Cuenta::where('email', $googleUser->getEmail())->first();

        if ($cuenta) {
            // Vincular google_id si llegó por email (primera vez con Google)
            if (!$cuenta->google_id) {
                $cuenta->update(['google_id' => $googleUser->getId()]);
            }

            if ($cuenta->estado !== 'activo') {
                $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
                return redirect("{$frontendUrl}/auth/google/callback?error=cuenta_suspendida");
            }
        } else {
            // Crear nueva cuenta
            [$nombre, $apellido] = $this->splitNombre($googleUser->getName());

            $cuenta = Cuenta::create([
                'nombre'            => $nombre,
                'apellido'          => $apellido,
                'email'             => $googleUser->getEmail(),
                'google_id'         => $googleUser->getId(),
                'avatar'            => $googleUser->getAvatar(),
                'email_verified_at' => now(),
                'estado'            => 'activo',
            ]);
        }

        $token       = $cuenta->createToken('tienda-google')->plainTextToken;
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));

        return redirect("{$frontendUrl}/auth/google/callback?token=" . urlencode($token));
    }

    private function splitNombre(?string $nombre): array
    {
        if (!$nombre) return ['Usuario', 'Google'];
        $partes = explode(' ', trim($nombre), 2);
        return [$partes[0], $partes[1] ?? ''];
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function formatearCuenta(Cuenta $cuenta, bool $conDirecciones = false): array
    {
        $data = [
            'id'             => $cuenta->id,
            'nombre'         => $cuenta->nombre,
            'apellido'       => $cuenta->apellido,
            'nombre_completo'=> $cuenta->nombre_completo,
            'email'          => $cuenta->email,
            'telefono'       => $cuenta->telefono,
            'avatar'         => $cuenta->avatar,
            'puntos_saldo'   => $cuenta->puntos_saldo,
            'estado'         => $cuenta->estado,
            'tiene_password' => !is_null($cuenta->password),
            'creado_en'      => $cuenta->created_at?->toDateString(),
        ];

        if ($conDirecciones && $cuenta->relationLoaded('direcciones')) {
            $data['direcciones'] = $cuenta->direcciones->map(fn($d) => [
                'id'             => $d->id,
                'alias'          => $d->alias,
                'nombre_receptor'=> $d->nombre_receptor,
                'telefono'       => $d->telefono,
                'departamento'   => $d->departamento,
                'municipio'      => $d->municipio,
                'direccion'      => $d->direccion,
                'referencias'    => $d->referencias,
                'es_principal'   => $d->es_principal,
            ]);
        }

        return $data;
    }
}