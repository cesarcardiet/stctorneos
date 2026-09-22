<?php

namespace Tests\Feature;

use App\Models\PlayerDocument;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_view_and_download_own_information(): void
    {
        $this->seed(DatabaseSeeder::class);

        $playerUser = User::query()->where('email', 'jugador@stctorneos.demo')->firstOrFail();
        $player = $playerUser->linkedPlayer();
        $this->assertNotNull($player);

        $this->actingAs($playerUser)
            ->get(route('workspace.player.home'))
            ->assertOk()
            ->assertSee('Mis documentos')
            ->assertSee('Ver torneos');

        $this->actingAs($playerUser)
            ->get(route('workspace.player.ficha'))
            ->assertOk()
            ->assertSee('Documentación')
            ->assertSee('Descargar ficha completa');

        $this->actingAs($playerUser)
            ->get(route('workspace.player.documents'))
            ->assertOk()
            ->assertSee('Mis documentos')
            ->assertSee('Constancia de autorización del tutor')
            ->assertDontSee('DNI frente')
            ->assertDontSee('Cobertura médica');

        $this->actingAs($playerUser)
            ->get(route('workspace.player.export'))
            ->assertOk()
            ->assertSee('Ficha del jugador')
            ->assertSee($player->fullName());

        $document = PlayerDocument::query()->where('player_id', $player->id)->whereNotNull('file_path')->first();
        if ($document) {
            $this->actingAs($playerUser)
                ->get(route('workspace.player.documents.download', $document))
                ->assertOk();
        }

        $otherDocument = PlayerDocument::query()->where('player_id', '!=', $player->id)->whereNotNull('file_path')->first();
        if ($otherDocument) {
            $this->actingAs($playerUser)
                ->get(route('workspace.player.documents.download', $otherDocument))
                ->assertForbidden();
        }
    }

    public function test_player_can_view_performance_history(): void
    {
        $this->seed(DatabaseSeeder::class);

        $playerUser = User::query()->where('email', 'jugador@stctorneos.demo')->firstOrFail();

        $this->actingAs($playerUser)
            ->get(route('workspace.player.home'))
            ->assertOk()
            ->assertSee('Mis estadísticas')
            ->assertSee('Ver historial completo');

        $this->actingAs($playerUser)
            ->get(route('workspace.player.stats'))
            ->assertOk()
            ->assertSee('Historial de partidos')
            ->assertSee('Goles')
            ->assertSee('Asistencias')
            ->assertDontSee('Planillas en blanco');
    }

    public function test_player_login_redirects_to_portal(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post(route('login.perform'), [
            'email' => 'jugador@stctorneos.demo',
            'password' => 'stcdemo',
        ])->assertRedirect(route('workspace.player.home'));
    }
}
