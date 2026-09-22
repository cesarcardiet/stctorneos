<?php

use App\Models\Invitation;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->string('code', 12)->nullable()->unique()->after('token');
        });

        Invitation::query()->whereNull('code')->each(function (Invitation $invitation): void {
            do {
                $code = Invitation::makeCode();
            } while (Invitation::query()->where('code', $code)->exists());

            $invitation->update(['code' => $code]);
        });

        Invitation::query()
            ->where('scope_type', 'App\\Models\\Player')
            ->update(['scope_type' => 'player']);

        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'players.view'],
            [
                'group' => 'Jugadores',
                'name' => 'Ver portal del jugador',
                'description' => 'Acceso al portal del jugador en la app.',
            ]
        );

        $role = Role::query()->firstOrCreate(
            ['slug' => 'jugador'],
            [
                'name' => 'Jugador',
                'scope' => 'player',
                'color' => '#4ade80',
                'description' => 'Consulta su ficha, partidos y notificaciones.',
            ]
        );

        if (! $role->permissions()->where('permissions.id', $permission->id)->exists()) {
            $role->permissions()->attach($permission->id);
        }
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
