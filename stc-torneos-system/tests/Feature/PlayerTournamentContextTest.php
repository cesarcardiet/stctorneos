<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use App\Support\WorkspaceContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerTournamentContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_browsing_other_tournament_does_not_jump_to_own_category(): void
    {
        $this->seed(DatabaseSeeder::class);

        $playerUser = User::query()->where('email', 'jugador@stctorneos.demo')->firstOrFail();
        $ownCategory = $playerUser->playerCategory();
        $this->assertNotNull($ownCategory);

        $otherTournament = Tournament::query()->where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $this->assertNotSame((int) $otherTournament->id, (int) $ownCategory->tournament_id);

        $this->actingAs($playerUser)
            ->get(route('workspace.tournaments.show', $otherTournament))
            ->assertOk()
            ->assertSee($otherTournament->name);

        $this->assertSame((int) $otherTournament->id, (int) session(WorkspaceContext::SESSION_KEY));

        $this->actingAs($playerUser)
            ->get(route('workspace.tournaments.show', $otherTournament))
            ->assertOk()
            ->assertDontSee($ownCategory->name);

        $this->actingAs($playerUser)
            ->get(route('workspace.player.home'))
            ->assertOk()
            ->assertSee($ownCategory->tournament?->name ?? '');
    }

    public function test_player_competition_links_only_for_own_tournament(): void
    {
        $this->seed(DatabaseSeeder::class);

        $playerUser = User::query()->where('email', 'jugador@stctorneos.demo')->firstOrFail();
        $ownCategory = $playerUser->playerCategory();
        $ownTournament = $ownCategory?->tournament;
        $this->assertNotNull($ownTournament);

        $this->actingAs($playerUser)
            ->get(route('workspace.tournaments.show', $ownTournament))
            ->assertOk()
            ->assertSee(route('workspace.categories.standings', $ownCategory), false);

        $otherTournament = Tournament::query()->where('slug', 'stc-buenos-aires-2026')->firstOrFail();

        $this->actingAs($playerUser)
            ->get(route('workspace.tournaments.show', $otherTournament))
            ->assertOk()
            ->assertSee(route('workspace.tournaments.show', $otherTournament), false)
            ->assertDontSee(route('workspace.categories.standings', $ownCategory), false);
    }
}
