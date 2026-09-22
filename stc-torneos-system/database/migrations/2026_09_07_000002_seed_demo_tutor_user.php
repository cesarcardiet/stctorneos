<?php

use App\Models\Guardian;
use App\Models\Player;
use App\Models\Role;
use App\Models\User;
use App\Support\TutorCredentials;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $role = Role::query()->where('slug', 'tutor')->first();
        if (! $role) {
            return;
        }

        $user = User::updateOrCreate(
            ['email' => 'tutor@stctorneos.demo'],
            [
                'name' => 'María Ruiz (Tutor demo)',
                'password' => TutorCredentials::defaultPassword(),
                'status' => 'active',
                'current_scope' => 'Mis jugadores',
                'email_verified_at' => now(),
            ]
        );

        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'scope_type' => 'system',
                'scope_id' => null,
                'assigned_at' => now(),
            ],
        ]);

        $player = Player::query()
            ->where('first_name', 'Lautaro')
            ->where('last_name', 'Ruiz')
            ->first();

        if ($player) {
            Guardian::updateOrCreate(
                ['player_id' => $player->id],
                [
                    'name' => 'María Ruiz (Tutor demo)',
                    'relationship' => 'Madre',
                    'email' => 'tutor@stctorneos.demo',
                    'phone' => '+54 11 5555-4321',
                    'consent_status' => 'pending',
                ]
            );

            if (! $user->tournament_id && $player->team?->tournament_id) {
                $user->update([
                    'tournament_id' => $player->team->tournament_id,
                    'current_scope' => $player->team->tournament?->name,
                ]);
            }
        }
    }

    public function down(): void
    {
        User::query()->where('email', 'tutor@stctorneos.demo')->delete();
    }
};
