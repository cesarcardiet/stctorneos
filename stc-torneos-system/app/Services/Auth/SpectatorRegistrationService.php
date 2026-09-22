<?php

namespace App\Services\Auth;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SpectatorRegistrationService
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function register(array $data): User
    {
        $email = Str::lower(trim($data['email']));

        if (User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Ya existe una cuenta con ese correo.',
            ]);
        }

        return DB::transaction(function () use ($data, $email): User {
            $role = Role::query()->where('slug', 'consulta')->firstOrFail();

            $user = User::query()->create([
                'name' => trim($data['name']),
                'email' => $email,
                'password' => Hash::make($data['password']),
                'status' => 'active',
            ]);

            $user->roles()->attach($role->id, [
                'scope_type' => 'system',
                'scope_id' => null,
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'module' => 'Acceso',
                'action' => 'register_spectator',
                'description' => 'Registro público espectador: '.$user->email,
            ]);

            return $user->fresh(['roles']);
        });
    }
}
