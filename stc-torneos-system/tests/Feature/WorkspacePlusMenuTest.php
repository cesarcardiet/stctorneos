<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspacePlusMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_plus_menu_with_m03_shortcuts_on_standings(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.categories.standings', $category))
            ->assertOk()
            ->assertSee('data-ws-open="plus-menu"', false)
            ->assertSee('Equipos', false)
            ->assertSee('Jugadores', false)
            ->assertSee('Imprimir clasificación', false)
            ->assertSee('Criterios de clasificación', false)
            ->assertSee('Ajustes 3 / 1 / 0', false)
            ->assertSee('data-ws-open="print-standings"', false);
    }

    public function test_delegate_cannot_see_plus_menu_on_standings(): void
    {
        $this->seed(DatabaseSeeder::class);

        $delegate = User::query()->where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();

        $this->actingAs($delegate)
            ->get(route('workspace.categories.standings', $category))
            ->assertOk()
            ->assertDontSee('data-ws-open="plus-menu"', false)
            ->assertDontSee('Imprimir clasificación', false);
    }

    public function test_standings_print_view_is_available_for_staff(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.categories.standings.print', $category))
            ->assertOk()
            ->assertSee('Clasificación · '.$category->name, false)
            ->assertSee('Imprimir / Guardar PDF', false)
            ->assertSee('Pos', false);
    }
}
