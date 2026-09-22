<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::updateOrCreate(
            ['slug' => 'guardian.self'],
            ['group' => 'Jugadores', 'name' => 'Portal del tutor']
        );

        $role = Role::updateOrCreate(
            ['slug' => 'tutor'],
            [
                'name' => 'Tutor',
                'scope' => 'guardian',
                'color' => '#7dd3fc',
                'description' => 'Completa fichas de sus hijos/jugadores a cargo.',
            ]
        );

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $role->permissions()->syncWithoutDetaching(
            Permission::query()->where('slug', 'dashboard.view')->pluck('id')
        );
    }

    public function down(): void
    {
        $role = Role::query()->where('slug', 'tutor')->first();
        if ($role) {
            $role->permissions()->detach();
            $role->delete();
        }

        Permission::query()->where('slug', 'guardian.self')->delete();
    }
};
