<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invitation;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RosterShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_delegate_generates_public_roster_link_and_guest_adds_player(): void
    {
        $this->seed(DatabaseSeeder::class);

        $delegate = User::where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $team = Team::where('name', 'Leones FC')->where('category_id', $category->id)->firstOrFail();

        $this->actingAs($delegate)
            ->post(route('workspace.categories.teams.roster-link', [$category, $team]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $invitation = Invitation::query()
            ->where('kind', 'roster')
            ->where('scope_id', $team->id)
            ->firstOrFail();

        $this->get(route('plantel.show', $invitation->token))
            ->assertOk()
            ->assertSee('Enlace de plantel')
            ->assertSee($team->name)
            ->assertSee('Añadir jugador');

        $this->post(route('plantel.store', $invitation->token), [
            'first_name' => 'Nico',
            'last_name' => 'Prueba Enlace',
            'document_number' => '50999888',
            'guardian_name' => 'María Prueba',
            'guardian_phone' => '111222333',
        ])->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('players', [
            'team_id' => $team->id,
            'first_name' => 'Nico',
            'last_name' => 'Prueba Enlace',
        ]);

        $this->actingAs($delegate)
            ->patch(route('workspace.categories.teams.roster-link.invalidate', [$category, $team]))
            ->assertRedirect();

        $this->post(route('plantel.store', $invitation->token), [
            'first_name' => 'Otro',
            'last_name' => 'Jugador',
        ])->assertForbidden();
    }

    public function test_arbitro_cannot_generate_roster_link(): void
    {
        $this->seed(DatabaseSeeder::class);

        $referee = User::where('email', 'arbitro@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $team = $category->teams()->firstOrFail();

        $this->actingAs($referee)
            ->post(route('workspace.categories.teams.roster-link', [$category, $team]))
            ->assertForbidden();
    }
}
