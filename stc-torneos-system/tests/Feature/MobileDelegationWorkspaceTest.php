<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileDelegationWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_delegate_workspace_includes_portfolio_and_team_metadata(): void
    {
        $this->seed(DatabaseSeeder::class);

        $delegate = User::where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $delegate->email,
            'password' => 'stcdemo',
        ])->json('data.token');

        $response = $this->withToken($token)
            ->getJson('/api/v1/workspace')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Leones FC'])
            ->assertJsonStructure([
                'data' => [
                    'portfolio',
                    'summary' => ['tournaments_count', 'teams_count', 'open_teams_count'],
                    'teams' => [['id', 'name', 'roster_editable', 'registration_label']],
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.summary.teams_count'));
    }

    public function test_delegate_can_view_team_roster_and_inscriptions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $delegate = User::where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $team = Team::where('name', 'Leones FC')->firstOrFail();
        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $delegate->email,
            'password' => 'stcdemo',
        ])->json('data.token');

        $this->withToken($token)
            ->getJson("/api/v1/workspace/teams/{$team->id}")
            ->assertOk()
            ->assertJsonPath('data.team.id', $team->id)
            ->assertJsonPath('data.category.id', $category->id);

        $this->withToken($token)
            ->getJson("/api/v1/workspace/teams/{$team->id}/players")
            ->assertOk()
            ->assertJsonPath('data.team.id', $team->id)
            ->assertJsonStructure(['data' => ['players']]);

        $this->withToken($token)
            ->getJson('/api/v1/workspace/inscriptions?category_id='.$category->id.'&team_id='.$team->id)
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['waiting_count', 'rejected_count', 'waiting', 'rejected'],
            ]);
    }
}
