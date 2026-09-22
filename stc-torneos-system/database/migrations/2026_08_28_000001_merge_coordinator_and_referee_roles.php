<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $assistant = Role::query()->updateOrCreate(
            ['slug' => 'asistente-arbitro'],
            [
                'name' => 'Asistente/árbitro',
                'scope' => 'match',
                'color' => '#ff7a18',
                'description' => 'Supervisa fixture, carga planillas y opera partidos.',
            ]
        );

        $permissionIds = Permission::query()
            ->whereIn('slug', [
                'dashboard.view',
                'matches.manage',
                'match_sheets.manage',
                'communications.manage',
            ])
            ->pluck('id')
            ->all();

        $assistant->permissions()->sync($permissionIds);

        $legacyRoleIds = Role::query()
            ->whereIn('slug', ['coordinador', 'arbitro'])
            ->pluck('id');

        if ($legacyRoleIds->isEmpty()) {
            return;
        }

        $userIds = DB::table('role_user')
            ->whereIn('role_id', $legacyRoleIds)
            ->pluck('user_id')
            ->unique()
            ->values();

        foreach ($userIds as $userId) {
            $alreadyAssigned = DB::table('role_user')
                ->where('role_id', $assistant->id)
                ->where('user_id', $userId)
                ->exists();

            if (! $alreadyAssigned) {
                $pivot = DB::table('role_user')
                    ->whereIn('role_id', $legacyRoleIds)
                    ->where('user_id', $userId)
                    ->orderByDesc('assigned_at')
                    ->first();

                DB::table('role_user')->insert([
                    'role_id' => $assistant->id,
                    'user_id' => $userId,
                    'scope_type' => $pivot->scope_type ?? null,
                    'scope_id' => $pivot->scope_id ?? null,
                    'assigned_by' => $pivot->assigned_by ?? null,
                    'assigned_at' => $pivot->assigned_at ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('role_user')->whereIn('role_id', $legacyRoleIds)->delete();
        DB::table('permission_role')->whereIn('role_id', $legacyRoleIds)->delete();
        DB::table('invitations')->whereIn('role_id', $legacyRoleIds)->update(['role_id' => $assistant->id]);
        Role::query()->whereIn('slug', ['coordinador', 'arbitro'])->delete();
    }

    public function down(): void
    {
        $assistant = Role::query()->where('slug', 'asistente-arbitro')->first();

        if (! $assistant) {
            return;
        }

        $coordinator = Role::query()->updateOrCreate(
            ['slug' => 'coordinador'],
            [
                'name' => 'Coordinador',
                'scope' => 'tournament',
                'color' => '#2eff94',
                'description' => 'Supervisa fixture, planillas y operación.',
            ]
        );

        $referee = Role::query()->updateOrCreate(
            ['slug' => 'arbitro'],
            [
                'name' => 'Arbitro',
                'scope' => 'match',
                'color' => '#ff7a18',
                'description' => 'Carga eventos, disciplina e informe arbitral.',
            ]
        );

        $coordinatorPermissions = Permission::query()
            ->whereIn('slug', ['dashboard.view', 'matches.manage', 'match_sheets.manage', 'communications.manage'])
            ->pluck('id')
            ->all();
        $refereePermissions = Permission::query()
            ->whereIn('slug', ['dashboard.view', 'match_sheets.manage'])
            ->pluck('id')
            ->all();

        $coordinator->permissions()->sync($coordinatorPermissions);
        $referee->permissions()->sync($refereePermissions);

        $userIds = DB::table('role_user')
            ->where('role_id', $assistant->id)
            ->pluck('user_id')
            ->unique()
            ->values();

        foreach ($userIds as $userId) {
            $email = DB::table('users')->where('id', $userId)->value('email');
            $targetRoleId = str_contains((string) $email, 'arbitro@') ? $referee->id : $coordinator->id;

            DB::table('role_user')->insert([
                'role_id' => $targetRoleId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('role_user')->where('role_id', $assistant->id)->delete();
        DB::table('permission_role')->where('role_id', $assistant->id)->delete();
        $assistant->delete();
    }
};
