<?php

namespace App\Services\Auth;

use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Player;
use App\Models\Role;
use App\Models\User;
use App\Services\GuardianAccountService;
use App\Services\PlayerAccountService;
use App\Support\InvitationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationRegistrationService
{
    public function __construct(
        private readonly UserScopeMaterializer $scopeMaterializer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(string $email, string $code): array
    {
        $invitation = $this->findUsableInvitation($email, $code);
        $scope = InvitationScope::describe($invitation->scope_type, $invitation->scope_id);
        $existingUser = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [Str::lower(trim($email))])
            ->first();

        return [
            'token' => $invitation->token,
            'code' => $invitation->code,
            'email' => $invitation->email,
            'name' => $invitation->name,
            'role' => $this->invitationRolePayload($invitation),
            'kind' => $invitation->kind ?: 'staff',
            'scope' => $scope,
            'expires_at' => $invitation->expires_at?->toIso8601String(),
            'requires_password' => true,
            'mode' => $existingUser ? 'activate' : 'register',
            'user_exists' => (bool) $existingUser,
        ];
    }

    /**
     * @param  array{name?: string, email: string, invitation_code: string, password: string, password_confirmation: string}  $data
     */
    public function accept(array $data): User
    {
        $email = Str::lower(trim($data['email']));
        $invitation = $this->findUsableInvitation($email, $data['invitation_code']);

        return DB::transaction(function () use ($data, $email, $invitation): User {
            $existingUser = User::query()
                ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                ->first();

            if ($existingUser) {
                $user = $this->activateExistingUser($existingUser, $data, $invitation);
            } else {
                $user = $this->createUser($data, $email, $invitation);
            }

            if ($invitation->role_id) {
                $this->scopeMaterializer->apply($user, $invitation);
            }

            $this->finalizeFamilyInvitation($user, $invitation);

            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'module' => 'Acceso',
                'action' => 'accept_invitation',
                'description' => 'Invitación aceptada: '.$user->email,
                'metadata' => [
                    'invitation_id' => $invitation->id,
                    'role_id' => $invitation->role_id,
                    'scope_type' => InvitationScope::normalize($invitation->scope_type),
                    'scope_id' => $invitation->scope_id,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $user->fresh(['roles.permissions']);
        });
    }

    public function findUsableInvitation(string $email, string $code): Invitation
    {
        $normalizedEmail = Str::lower(trim($email));
        $normalizedCode = $this->normalizeCode($code);

        $invitation = Invitation::query()
            ->with('role')
            ->where(function ($query) {
                $query
                    ->whereNull('kind')
                    ->orWhereIn('kind', ['staff', 'guardian', 'player']);
            })
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->where(function ($query) use ($normalizedCode, $code) {
                $raw = trim($code);
                $query
                    ->where('code', $normalizedCode)
                    ->orWhere('token', $raw)
                    ->orWhere('token', $normalizedCode)
                    ->orWhereRaw("REPLACE(LOWER(token), '-', '') = ?", [$normalizedCode])
                    ->orWhereRaw('LOWER(token) = ?', [Str::lower($raw)]);
            })
            ->first();

        if (! $invitation) {
            throw ValidationException::withMessages([
                'invitation_code' => 'No encontramos una invitación válida para ese correo y código.',
            ]);
        }

        if ($invitation->accepted_at || $invitation->status === 'accepted') {
            throw ValidationException::withMessages([
                'invitation_code' => 'Esta invitación ya fue usada. Podés iniciar sesión con tu correo.',
            ]);
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'invitation_code' => 'La invitación venció. Pedí una nueva al administrador.',
            ]);
        }

        if (! $invitation->isUsable()) {
            throw ValidationException::withMessages([
                'invitation_code' => 'La invitación venció o ya no está disponible.',
            ]);
        }

        return $invitation;
    }

    /**
     * @return array{slug: string, name: string}|null
     */
    private function invitationRolePayload(Invitation $invitation): ?array
    {
        if ($invitation->role) {
            return [
                'slug' => $invitation->role->slug,
                'name' => $invitation->role->name,
            ];
        }

        return match (true) {
            $invitation->isGuardian() => ['slug' => 'tutor', 'name' => 'Tutor'],
            $invitation->isPlayerPortal() => ['slug' => 'jugador', 'name' => 'Jugador'],
            default => null,
        };
    }

    private function finalizeFamilyInvitation(User $user, Invitation $invitation): void
    {
        if ($invitation->isGuardian()) {
            $player = Player::query()->find($invitation->player_id);
            if ($player) {
                app(GuardianAccountService::class)->ensureForGuardian(
                    $player,
                    $user->email,
                    $user->name,
                    $invitation->invited_by
                );
                $this->scopeMaterializer->apply($user, $invitation);
            }

            return;
        }

        if ($invitation->isPlayerPortal()) {
            $player = Player::query()->find($invitation->player_id);
            if ($player) {
                app(PlayerAccountService::class)->ensureForPlayer($player, $invitation->invited_by);
                $user->forceFill([
                    'player_id' => $player->id,
                    'name' => $user->name ?: $player->fullName(),
                ])->save();
                $this->scopeMaterializer->apply($user, $invitation);
            }
        }
    }

    /**
     * @param  array{name?: string, password: string}  $data
     */
    private function activateExistingUser(User $user, array $data, Invitation $invitation): User
    {
        if ($user->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'email' => 'Este correo ya tiene un acceso especial y no puede activarse por invitación.',
            ]);
        }

        if ($user->roles()->exists() && ! $user->roles()->where('roles.id', $invitation->role_id)->exists()) {
            $blocked = $user->hasRole('admin-torneo') || $user->hasRole('super-admin');
            if ($blocked) {
                throw ValidationException::withMessages([
                    'email' => 'Ya existe un usuario con ese correo y otro rol asignado.',
                ]);
            }
        }

        $user->update([
            'name' => filled($data['name'] ?? null) ? trim((string) $data['name']) : $user->name,
            'password' => Hash::make($data['password']),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        return $user;
    }

    /**
     * @param  array{name?: string, password: string}  $data
     */
    private function createUser(array $data, string $email, Invitation $invitation): User
    {
        if (! filled($data['name'] ?? null)) {
            throw ValidationException::withMessages([
                'name' => 'El nombre es obligatorio para crear la cuenta.',
            ]);
        }

        return User::create([
            'name' => trim((string) $data['name']),
            'email' => $email,
            'password' => Hash::make($data['password']),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function normalizeCode(string $code): string
    {
        return Str::upper(trim(str_replace([' ', '-'], '', $code)));
    }
}
