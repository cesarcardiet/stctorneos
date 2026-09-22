<?php

namespace Tests\Feature;

use App\Models\FixtureMatch;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileStaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_referee_can_list_and_open_staff_match_sheet(): void
    {
        $this->seed(DatabaseSeeder::class);

        $referee = User::where('email', 'arbitro@stctorneos.demo')->firstOrFail();
        $match = FixtureMatch::query()->whereNotNull('home_team_id')->firstOrFail();
        $match->update(['referee_user_id' => $referee->id]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $referee->email,
            'password' => 'stcdemo',
        ])->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v1/staff/matches?scope=today')
            ->assertOk()
            ->assertJsonStructure(['data' => ['scope', 'matches']]);

        $this->withToken($token)
            ->getJson("/api/v1/staff/matches/{$match->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'match' => ['id', 'title', 'status'],
                    'sheet' => ['id', 'status', 'events', 'incidents'],
                    'players' => ['home', 'away'],
                    'can_operate',
                ],
            ]);
    }

    public function test_referee_can_add_event_and_update_report(): void
    {
        $this->seed(DatabaseSeeder::class);

        $referee = User::where('email', 'arbitro@stctorneos.demo')->firstOrFail();
        $match = FixtureMatch::query()->whereNotNull('home_team_id')->firstOrFail();
        $match->update(['referee_user_id' => $referee->id, 'status' => 'live']);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $referee->email,
            'password' => 'stcdemo',
        ])->json('data.token');

        $this->withToken($token)
            ->postJson("/api/v1/staff/matches/{$match->id}/events", [
                'type' => 'goal',
                'team_id' => $match->home_team_id,
                'minute' => 12,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->withToken($token)
            ->postJson("/api/v1/staff/matches/{$match->id}/report", [
                'notes' => 'Partido sin incidentes relevantes.',
            ])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Partido sin incidentes relevantes.');
    }

    public function test_delegate_cannot_access_staff_matches(): void
    {
        $this->seed(DatabaseSeeder::class);

        $delegate = User::where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $delegate->email,
            'password' => 'stcdemo',
        ])->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v1/staff/matches')
            ->assertForbidden();
    }
}
