<?php

namespace App\Http\Controllers\Api;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetApiController extends ApiController
{
    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
        ]);

        Password::sendResetLink(['email' => $data['email']]);

        return $this->ok(null, 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.');
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $data,
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->fail('No pudimos restablecer la contraseña. Probá solicitar un enlace nuevo.', 422);
        }

        return $this->ok(null, 'Contraseña actualizada. Ya podés ingresar.');
    }
}
