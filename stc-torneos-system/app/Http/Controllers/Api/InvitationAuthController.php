<?php

namespace App\Http\Controllers\Api;

use App\Services\Auth\InvitationRegistrationService;
use App\Services\Auth\MobileUserPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationAuthController extends ApiController
{
    public function __construct(
        private readonly InvitationRegistrationService $registrationService,
        private readonly MobileUserPayloadBuilder $payloadBuilder,
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'invitation_code' => ['required', 'string', 'max:120'],
        ]);

        try {
            $payload = $this->registrationService->preview(
                $data['email'],
                $data['invitation_code'],
            );
        } catch (ValidationException $exception) {
            return $this->fail(
                collect($exception->errors())->flatten()->first() ?: 'Invitación inválida.',
                422,
                $exception->errors(),
            );
        }

        return $this->ok($payload);
    }

    public function accept(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'invitation_code' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $user = $this->registrationService->accept($data);
        } catch (ValidationException $exception) {
            return $this->fail(
                collect($exception->errors())->flatten()->first() ?: 'No pudimos activar la cuenta.',
                422,
                $exception->errors(),
            );
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
        ], null, 201);
    }
}
