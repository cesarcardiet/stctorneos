<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\Auth\MobileUserPayloadBuilder;
use App\Services\Auth\SpectatorRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends ApiController
{
    public function __construct(
        private readonly MobileUserPayloadBuilder $payloadBuilder,
        private readonly SpectatorRegistrationService $spectatorRegistration,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $email = Str::lower(trim($data['email']));
        $user = User::query()->with('roles')->whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return $this->fail('Las credenciales no coinciden.', 422);
        }

        if ($user->status !== 'active') {
            $message = match ($user->status) {
                'pending' => 'Tu acceso todavía está pendiente de aprobación.',
                'suspended' => 'Tu acceso está suspendido.',
                'revoked' => 'Tu acceso fue revocado.',
                default => 'Tu usuario no tiene acceso activo.',
            };

            return $this->fail($message, 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $device = filled($data['device_name'] ?? null)
            ? Str::slug($data['device_name'])
            : 'stc-mobile';
        $token = $user->createToken('stc-mobile:'.$device)->plainTextToken;

        return $this->ok([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->payloadBuilder->build($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles');

        return $this->ok($this->payloadBuilder->build($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->ok(null, 'Sesión cerrada.');
    }

    public function registerSpectator(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $this->spectatorRegistration->register($data);
        $device = filled($data['device_name'] ?? null)
            ? Str::slug($data['device_name'])
            : 'stc-mobile';
        $token = $user->createToken('stc-mobile:'.$device)->plainTextToken;

        return $this->ok([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->payloadBuilder->build($user),
        ], 'Cuenta creada.', 201);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
        ]);

        $user = $request->user();
        $user->fill($data)->save();

        return $this->ok($this->payloadBuilder->build($user->fresh(['roles'])));
    }
}
