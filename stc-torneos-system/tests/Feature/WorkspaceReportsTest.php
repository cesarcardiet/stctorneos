<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_shows_print_reports_section(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.categories.settings', $category))
            ->assertOk()
            ->assertSee('Imprimir reportes', false)
            ->assertSee('report-teams', false)
            ->assertSee('report-players', false)
            ->assertSee('report-credentials', false)
            ->assertSee('report-acta', false);
    }

    public function test_admin_can_open_teams_report(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.categories.reports.teams', [$category, 'columns' => ['pts', 'j', 'g']]))
            ->assertOk()
            ->assertSee('Listado de equipos', false)
            ->assertSee('Descargar PDF', false);

        $this->actingAs($admin)
            ->get(route('workspace.categories.reports.teams', [$category, 'columns' => ['pts', 'j', 'g'], 'format' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_can_open_players_and_credentials_reports(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();
        $team = Team::query()->where('category_id', $category->id)->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.categories.reports.players', [$category, 'team_ids' => [$team->id]]))
            ->assertOk()
            ->assertSee('Listado de jugadores', false)
            ->assertSee($team->name, false);

        $this->actingAs($admin)
            ->get(route('workspace.categories.reports.credentials', [$category, 'team_ids' => [$team->id]]))
            ->assertOk()
            ->assertSee('Credenciales de jugadores', false);
    }

    public function test_delegate_cannot_open_reports(): void
    {
        $this->seed(DatabaseSeeder::class);

        $delegate = User::query()->where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();

        $this->actingAs($delegate)
            ->get(route('workspace.categories.settings', $category))
            ->assertForbidden();

        $this->actingAs($delegate)
            ->get(route('workspace.categories.reports.teams', $category))
            ->assertForbidden();
    }

    public function test_health_endpoint_returns_ok(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@stctorneos.demo')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.health'))
            ->assertOk()
            ->assertJson(['ok' => true]);
    }
}
