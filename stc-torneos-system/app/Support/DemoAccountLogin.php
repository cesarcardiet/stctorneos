<?php

namespace App\Support;

use App\Models\Player;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DemoAccountLogin
{
    /**
     * @return array{user: User, password: string}|null
     */
    public static function resolve(string $email, string $password): ?array
    {
        $email = self::normalizeEmail($email);
        $password = self::normalizePassword($password);

        if ($email === '') {
            return null;
        }

        $expected = self::expectedPassword($email);
        if ($expected !== null && $password !== $expected) {
            return null;
        }

        $user = User::query()
            ->with('roles')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();

        if (! $user) {
            return null;
        }

        self::ensureDemoAccount($user, $email);

        $user = $user->fresh(['roles']);
        $checkPassword = $expected ?? $password;

        if (! Hash::check($checkPassword, $user->getAuthPassword())) {
            if ($expected !== null) {
                $user->forceFill([
                    'password' => $expected,
                    'status' => 'active',
                ])->save();
                $user = $user->fresh(['roles']);
            }

            if (! Hash::check($checkPassword, $user->getAuthPassword())) {
                return null;
            }
        }

        return [
            'user' => $user,
            'password' => $checkPassword,
        ];
    }

    public static function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));

        return preg_replace('/\p{Cf}/u', '', $email) ?? $email;
    }

    public static function normalizePassword(string $password): string
    {
        $password = trim($password);

        return preg_replace('/\p{Cf}/u', '', $password) ?? $password;
    }

    public static function expectedPassword(string $email): ?string
    {
        $email = self::normalizeEmail($email);

        return match ($email) {
            'tutor@stctorneos.demo' => TutorCredentials::defaultPassword(),
            'jugador@stctorneos.demo' => DemoCredentials::password(),
            default => AdminNavigation::demoPasswords()[$email] ?? null,
        };
    }

    public static function isDemoEmail(string $email): bool
    {
        $email = self::normalizeEmail($email);

        foreach (AdminNavigation::demoAccounts() as [, , $demoEmail]) {
            if (self::normalizeEmail($demoEmail) === $email) {
                return true;
            }
        }

        return false;
    }

    private static function ensureDemoAccount(User $user, string $email): void
    {
        $email = self::normalizeEmail($email);

        if ($email === 'jugador@stctorneos.demo') {
            $role = Role::query()->where('slug', 'jugador')->first();
            if ($role && ! $user->hasRole('jugador')) {
                $user->roles()->syncWithoutDetaching([
                    $role->id => ['assigned_at' => now()],
                ]);
            }

            if (! $user->player_id) {
                $player = Player::query()
                    ->where('first_name', 'Lautaro')
                    ->where('last_name', 'Ruiz')
                    ->first();
                if ($player) {
                    $user->player_id = $player->id;
                }
            }
        }

        if ($email === 'tutor@stctorneos.demo') {
            $role = Role::query()->where('slug', 'tutor')->first();
            if ($role && ! $user->hasRole('tutor')) {
                $user->roles()->syncWithoutDetaching([
                    $role->id => ['assigned_at' => now()],
                ]);
            }
        }

        if ($user->isDirty()) {
            $user->status = 'active';
            $user->save();
            $user->unsetRelation('roles');
        }
    }
}
