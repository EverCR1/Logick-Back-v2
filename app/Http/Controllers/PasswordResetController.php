<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Models\User;
use App\Mail\PasswordResetMail;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    const TOKEN_EXPIRY_MINUTES = 60;

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user || $user->estado === 'inactivo') {
            return response()->json([
                'message' => 'Si el correo está registrado, recibirás las instrucciones en breve.',
            ]);
        }

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => Hash::make($token),
            'created_at' => Carbon::now(),
        ]);

        $resetUrl = config('app.dashboard_url') . '/password/reset/' . $token . '?email=' . urlencode($user->email);

        Mail::to($user->email)->send(new PasswordResetMail($user, $resetUrl));

        return response()->json([
            'message' => 'Si el correo está registrado, recibirás las instrucciones en breve.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email',
            'token'                 => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ], [
            'password.min'       => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'El enlace de recuperación no es válido o ya fue usado.',
            ], 422);
        }

        $createdAt = Carbon::parse($record->created_at);
        if ($createdAt->addMinutes(self::TOKEN_EXPIRY_MINUTES)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'El enlace de recuperación ha expirado. Solicita uno nuevo.',
            ], 422);
        }

        if (!Hash::check($request->token, $record->token)) {
            return response()->json([
                'message' => 'El enlace de recuperación no es válido.',
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado.'], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.',
        ]);
    }
}
