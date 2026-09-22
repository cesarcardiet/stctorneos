<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_manage_roles_and_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $role = Role::where('slug', 'delegado')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('Roles del sistema')
            ->assertSee('Delegado');

        $this->actingAs($admin)
            ->get(route('admin.roles.edit', $role))
            ->assertOk()
            ->assertSee('players.approve');

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $role), [
                'name' => 'Delegado club',
                'description' => 'Gestiona su club.',
                'color' => '#ffc84d',
                'permission_ids' => $role->permissions()->pluck('permissions.id')->all(),
            ])
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'Delegado club',
        ]);
    }

    public function test_admin_torneo_cannot_open_roles_module(): void
    {
        $this->seed(DatabaseSeeder::class);

        $lucia = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();

        $this->actingAs($lucia)
            ->get(route('admin.roles.index'))
            ->assertForbidden();
    }
}
