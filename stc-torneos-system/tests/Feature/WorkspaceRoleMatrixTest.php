<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Team;
use App\Models\User;
use App\Support\WorkspaceAccess;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceRoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private Team $leones;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $this->leones = Team::where('name', 'Leones FC')->where('category_id', $this->category->id)->firstOrFail();
    }

    public function test_documented_matrix_has_all_roles(): void
    {
        $matrix = WorkspaceAccess::documentedMatrix();
        $roles = ['super-admin', 'admin-torneo', 'delegado', 'asistente-arbitro', 'asistente-mesa', 'jugador', 'tutor'];

        foreach ($matrix as $screen => $row) {
            foreach ($roles as $role) {
                $this->assertArrayHasKey($role, $row, "Falta rol {$role} en pantalla {$screen}");
            }
        }
    }

    public function test_delegate_can_manage_roster_and_generate_link(): void
    {
        $delegate = User::where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $access = WorkspaceAccess::for($delegate);

        $this->assertTrue($access->canManageTeamRoster($this->leones));
        $this->assertTrue($access->canShareRosterLink($this->leones));
        $this->assertFalse($access->canViewSettings());
        $this->assertFalse($access->canAccessAdminWeb());

        $this->actingAs($delegate)
            ->get(route('workspace.categories.settings', $this->category))
            ->assertForbidden();

        $this->actingAs($delegate)
            ->post(route('workspace.categories.teams.roster-link', [$this->category, $this->leones]))
            ->assertRedirect();
    }

    public function test_referee_can_view_team_but_not_settings_or_roster_link(): void
    {
        $referee = User::where('email', 'coordinador@stctorneos.demo')->firstOrFail();
        $access = WorkspaceAccess::for($referee);

        $this->assertTrue($access->canViewCategoryCompetition($this->category));
        $this->assertFalse($access->canManageTeamRoster($this->leones));
        $this->assertFalse($access->canShareRosterLink($this->leones));
        $this->assertTrue($access->canOperateMatches());
        $this->assertFalse($access->canViewSettings());

        $this->actingAs($referee)
            ->get(route('workspace.categories.teams.show', [$this->category, $this->leones]))
            ->assertOk()
            ->assertSee('Enlace para cargar plantel');

        $this->actingAs($referee)
            ->get(route('workspace.categories.settings', $this->category))
            ->assertForbidden();

        $this->actingAs($referee)
            ->post(route('workspace.categories.teams.roster-link', [$this->category, $this->leones]))
            ->assertForbidden();
    }

    public function test_admin_torneo_can_configure_category(): void
    {
        $admin = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $access = WorkspaceAccess::for($admin);

        $this->assertTrue($access->canViewSettings());
        $this->assertTrue($access->canShareRosterLink($this->leones));
        $this->assertTrue($access->canAccessAdminWeb());
        $this->assertFalse($access->canManageUsers());

        $this->actingAs($admin)
            ->get(route('workspace.categories.settings', $this->category))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_player_portal_is_isolated(): void
    {
        $player = User::where('email', 'jugador@stctorneos.demo')->firstOrFail();
        $access = WorkspaceAccess::for($player);

        $this->assertTrue($access->isJugador());
        $this->assertFalse($access->canAccessAdminWeb());
        $this->assertFalse($access->canManageTeamRoster($this->leones));

        $this->actingAs($player)
            ->get(route('workspace.player.home'))
            ->assertOk();

        $this->actingAs($player)
            ->get(route('workspace.categories.settings', $this->category))
            ->assertForbidden();
    }

    public function test_account_page_shows_role_capabilities(): void
    {
        $delegate = User::where('email', 'delegado@stctorneos.demo')->firstOrFail();

        $this->actingAs($delegate)
            ->get(route('workspace.account'))
            ->assertOk()
            ->assertSee('Qué podés hacer en Operación')
            ->assertSee('Generá el enlace');
    }
}
