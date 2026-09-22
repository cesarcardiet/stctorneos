<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_api_serves_live_tournament_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'mesa@stctorneos.demo',
            'password' => 'stcdemo',
        ])->assertStatus(403);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'delegado@stctorneos.demo',
            'password' => 'stcdemo',
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.user.email', 'delegado@stctorneos.demo');

        $token = $login->json('data.token');
        $this->assertNotEmpty($token);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.tournament.name', 'Santa Teresita Cup 2026')
            ->assertJsonFragment(['title' => 'Fixture de Final Oro confirmado']);

        $this->getJson('/api/v1/tournaments')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'santa-teresita-cup-2026']);

        $this->getJson("/api/v1/categories/{$category->id}/standings")
            ->assertOk()
            ->assertJsonFragment(['name' => 'Buen Ayre']);

        $this->getJson("/api/v1/categories/{$category->id}/rankings")
            ->assertOk()
            ->assertJsonFragment(['name' => 'Thiago Martínez']);

        $this->getJson("/api/v1/categories/{$category->id}/brackets")
            ->assertOk()
            ->assertJsonFragment(['stage' => 'Final Oro']);

        $this->getJson('/api/v1/content')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'fixture-final-oro-confirmado'])
            ->assertJsonMissing(['slug' => 'cambio-horario-cancha-3']);

        $this->getJson('/api/v1/content/cambio-horario-cancha-3')
            ->assertNotFound();

        $this->getJson('/api/v1/content/reglamento-fair-play')
            ->assertOk()
            ->assertJsonPath('data.title', 'Reglamento de Fair Play');

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'Delegado');

        $this->withToken($token)
            ->getJson('/api/v1/favorites')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Leones FC']);

        $team = Team::where('name', 'Unión FC')->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/v1/favorites/teams/'.$team->id)
            ->assertOk()
            ->assertJsonPath('data.favorited', true);

        $this->withToken($token)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Resultado publicado: San Lorenzo 3-2 Pampero']);
    }
}
