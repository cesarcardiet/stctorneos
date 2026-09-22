<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Category;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriticalCrudSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_team_and_player_crud_does_not_return_500(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Categoría 2009')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('workspace.categories.teams.store', $category), [
                'name' => 'Equipo Smoke CRUD',
                'group_name' => 'A',
            ])
            ->assertRedirect();

        $team = Team::where('name', 'Equipo Smoke CRUD')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('workspace.categories.teams.update', [$category, $team]), [
                'name' => 'Equipo Smoke Editado',
                'delegation_name' => 'Equipo Smoke Editado',
                'group_name' => 'B',
                'status' => 'approved',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('workspace.categories.players.store', $category), [
                'team_id' => $team->id,
                'last_name' => 'Prueba',
                'first_name' => 'Jugador',
                'document_number' => '50999888',
                'guardian_name' => 'Tutor Prueba',
                'guardian_phone' => '1155550000',
                'guardian_relationship' => 'Padre',
            ])
            ->assertRedirect();

        $player = Player::where('document_number', '50999888')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('workspace.categories.players.update', [$category, $player]), [
                'first_name' => 'Jugador Editado',
                'last_name' => 'Prueba',
                'document_number' => '50999888',
                'birth_date' => '2014-03-12',
                'position' => 'Delantero',
                'jersey_number' => 9,
                'status' => 'pending',
                'guardian_name' => 'Tutor Prueba',
                'guardian_phone' => '1155550000',
                'guardian_relationship' => 'Padre',
            ])
            ->assertRedirect();

        $this->assertSame('Jugador Editado', $player->fresh()->first_name);

        $this->actingAs($admin)
            ->delete(route('workspace.categories.players.destroy', [$category, $player]))
            ->assertRedirect();

        $this->actingAs($admin)
            ->delete(route('workspace.categories.teams.destroy', [$category, $team]))
            ->assertRedirect();

        $this->assertDatabaseMissing('players', ['id' => $player->id]);
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    public function test_notifications_documents_and_registration_crud_does_not_return_500(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $tournament = Tournament::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.communications.notifications.store'), [
                'title' => 'Aviso CRUD Smoke',
                'body' => 'Mensaje inicial',
                'channel' => 'in_app',
                'audience' => 'all',
            ])
            ->assertRedirect();

        $notification = AppNotification::where('title', 'Aviso CRUD Smoke')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.communications.notifications.edit', $notification))
            ->assertOk();

        $this->actingAs($admin)
            ->put(route('admin.communications.notifications.update', $notification), [
                'title' => 'Aviso CRUD Editado',
                'body' => 'Mensaje editado',
                'channel' => 'in_app',
                'audience' => 'staff',
            ])
            ->assertRedirect();

        $this->assertSame('Aviso CRUD Editado', $notification->fresh()->title);

        $this->actingAs($admin)
            ->delete(route('admin.communications.notifications.destroy', $notification))
            ->assertRedirect();

        $this->assertDatabaseMissing('app_notifications', ['id' => $notification->id]);

        $this->actingAs($admin)
            ->patch(route('admin.tournaments.registrations', $tournament), ['open' => false])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('admin.tournaments.registrations', $tournament), ['open' => true])
            ->assertRedirect();

        $document = PlayerDocument::query()
            ->get()
            ->first(fn (PlayerDocument $document) => ! $document->wasSignedByGuardian());

        $this->assertNotNull($document);

        $this->actingAs($admin)
            ->patch(route('admin.players.documents.review', [$document->player_id, $document]), [
                'status' => 'approved',
            ])
            ->assertRedirect();

        $this->assertSame('approved', $document->fresh()->status);
    }
}
