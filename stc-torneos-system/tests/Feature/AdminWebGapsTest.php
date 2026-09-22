<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Category;
use App\Models\DocumentRequirement;
use App\Models\FixtureMatch;
use App\Models\Invitation;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminWebGapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_invitation_lifecycle_and_public_ficha(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $lucia = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $player = Player::where('first_name', 'Thiago')->where('last_name', 'Martínez')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.players.invite', $player), ['email' => 'tutor.demo@stc.test'])
            ->assertRedirect();

        $invitation = Invitation::query()->where('player_id', $player->id)->where('kind', 'guardian')->latest('id')->firstOrFail();
        $this->assertSame('generated', $invitation->family_status);
        $this->assertSame('tutor.demo@stc.test', $invitation->email);

        $this->get(route('ficha.show', $invitation->token))
            ->assertOk()
            ->assertSee($player->fullName())
            ->assertSee('Confirmar datos del delegado')
            ->assertSee('D.N.I. del tutor')
            ->assertSee('Información médica')
            ->assertSee('DNI frente')
            ->assertSee('AUTORIZACIÓN Y ACEPTACIÓN DE RESPONSABILIDAD')
            ->assertSee('Siguiente')
            ->assertSee('Enviar ficha del tutor')
            ->assertDontSee('Guardar avance');

        $this->assertSame('accessed', $invitation->fresh()->family_status);

        $documentFiles = [];
        foreach (Player::tutorUploadDocumentTypes() as $type) {
            $documentFiles[$type] = UploadedFile::fake()->image(str_replace(' ', '-', strtolower($type)).'.jpg');
        }

        $this->post(route('ficha.update', $invitation->token), [
            'first_name' => $player->first_name,
            'last_name' => $player->last_name,
            'guardian_first_name' => 'María',
            'guardian_last_name' => 'Martínez',
            'guardian_name' => 'María Martínez',
            'guardian_document_number' => '27123456',
            'relationship' => 'Madre',
            'email' => 'tutor.demo@stc.test',
            'phone' => '111',
            'guardian_alternate_name' => 'Padre Demo',
            'guardian_alternate_phone' => '222',
            'document_number' => '50111222',
            'birth_date' => '2014-05-10',
            'nationality' => 'Argentina',
            'address' => 'Av. Colón 1234',
            'kit_size' => 'M',
            'jersey_number' => 7,
            'position' => 'Delantero',
            'preferred_foot' => 'Derecha',
            'height' => '1.62 m',
            'weight' => '52 kg',
            'blood_type' => 'O+',
            'medical_coverage' => 'OSDE',
            'allergies' => 'Ninguna',
            'medication' => 'Ninguna',
            'illnesses' => 'Ninguna',
            'restrictions' => 'Ninguna',
            'emergency_contact' => 'María 111',
            'vaccination_calendar_complete' => '1',
            'consent' => '1',
            'complete' => '1',
            'auth' => ['Autorización', 'Uso de imagen', 'Apto médico'],
            'document_files' => $documentFiles,
        ])->assertRedirect(route('ficha.show', $invitation->token));

        $player->refresh()->load(['guardian', 'documents']);

        $this->assertSame('27123456', $player->guardian?->document_number);
        $this->assertSame('approved', $player->guardian?->consent_status);
        $this->assertSame('50111222', $player->document_number);
        $this->assertSame('2014-05-10', $player->birth_date?->format('Y-m-d'));
        $this->assertSame('M', $player->kit_size);
        $this->assertEquals(7, $player->jersey_number);
        $this->assertSame('Delantero', $player->position);
        $this->assertSame('1.62 m', $player->height);
        $this->assertSame('52 kg', $player->weight);

        foreach (['Autorización', 'Uso de imagen'] as $type) {
            $document = $player->documentByType($type);
            $this->assertNotNull($document?->uploaded_by_name, $type);
            $this->assertSame('approved', $document->status, $type);
        }

        $apto = $player->documentByType('Apto médico');
        $this->assertNotNull($apto);
        $this->assertSame('approved', $apto->status);
        $this->assertNotNull($apto->notes);

        foreach (Player::uploadDocumentTypes() as $type) {
            $this->assertNotNull($player->documentByType($type)?->file_path, $type);
        }

        $certificate = $player->guardianCertificate();
        $this->assertNotNull($certificate?->file_path);
        $this->assertSame('approved', $certificate->status);
        $this->assertTrue(is_file(public_path($certificate->file_path)));

        if ($certificate->file_path && str_starts_with($certificate->file_path, 'images/players/docs/')) {
            File::delete(public_path($certificate->file_path));
        }

        $this->get(route('ficha.show', $invitation->token))
            ->assertOk()
            ->assertSee('Autorizaciones aceptadas')
            ->assertSee('Autorización aceptada')
            ->assertSee('Ver constancia de autorización');

        $this->assertSame('completed', $invitation->fresh()->family_status);

        $this->actingAs($admin)
            ->post(route('admin.players.invite', $player), ['email' => 'tutor.demo@stc.test'])
            ->assertRedirect();

        $latest = Invitation::query()->where('player_id', $player->id)->where('kind', 'guardian')->latest('id')->firstOrFail();
        $this->actingAs($admin)
            ->patch(route('admin.players.invite.invalidate', $player))
            ->assertRedirect();
        $this->assertSame('invalidated', $latest->fresh()->family_status);

        $this->get(route('ficha.show', $latest->token))->assertStatus(410);

        $this->actingAs($admin)
            ->get(route('admin.players.credential', $player))
            ->assertOk()
            ->assertSee('Credencial');

        $foreign = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $foreignPlayer = Player::query()->whereHas('team', fn ($query) => $query->where('tournament_id', $foreign->id))->first();
        if ($foreignPlayer) {
            $this->actingAs($lucia)
                ->post(route('admin.players.invite', $foreignPlayer), ['email' => 'otro@stc.test'])
                ->assertForbidden();
        }
    }

    public function test_document_requirements_and_apply(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $tournament = Tournament::where('slug', 'santa-teresita-cup-2026')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.documents.requirements'))
            ->assertOk()
            ->assertSee('Requisitos documentales');

        $this->actingAs($admin)
            ->post(route('admin.documents.requirements.store'), [
                'tournament_id' => $tournament->id,
                'type' => 'Seguro de viaje',
                'required' => '1',
                'has_expiration' => '1',
                'validity_days' => 365,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('document_requirements', [
            'type' => 'Seguro de viaje',
            'tournament_id' => $tournament->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.documents.requirements.apply'))
            ->assertRedirect();

        $this->assertTrue(
            DocumentRequirement::query()->where('type', 'Seguro de viaje')->exists()
        );
    }

    public function test_jornada_officials_live_and_penalties(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $referee = User::where('email', 'arbitro@stctorneos.demo')->firstOrFail();
        $match = FixtureMatch::query()->whereDate('scheduled_at', '2026-12-14')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.fixture.jornada', ['date' => '2026-12-14']))
            ->assertOk()
            ->assertSee('Mapa de la fecha');

        $this->actingAs($admin)
            ->patch(route('admin.fixture.status', $match), [
                'status' => 'live',
                'home_score' => 1,
                'away_score' => 0,
                'minute' => '31:10',
                'period' => '1t',
                'referee_user_id' => $referee->id,
                'scorer_user_id' => $referee->id,
            ])
            ->assertRedirect();

        $match->refresh();
        $this->assertSame('live', $match->status);
        $this->assertSame('1t', $match->period);
        $this->assertSame($referee->id, $match->referee_user_id);
        $this->assertSame('Martín Sosa', $match->referee_name);

        $this->actingAs($admin)
            ->post(route('admin.fixture.penalties.store', $match), [
                'team_id' => $match->home_team_id,
                'scored' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('match_penalty_kicks', [
            'match_id' => $match->id,
            'team_id' => $match->home_team_id,
            'scored' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.fixture.show', $match))
            ->assertOk()
            ->assertSee('Serie de penales');
    }

    public function test_sanctions_rating_team_of_round_and_plaques(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $team = $category->teams()->firstOrFail();
        $match = FixtureMatch::query()->where('category_id', $category->id)->whereIn('status', ['finished', 'validated'])->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.results.sanctions'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.results.sanctions.store'), [
                'tournament_id' => $category->tournament_id,
                'category_id' => $category->id,
                'team_id' => $team->id,
                'type' => 'points',
                'title' => 'Descuento de prueba',
                'points_delta' => -3,
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sanctions', [
            'title' => 'Descuento de prueba',
            'points_delta' => -3,
            'team_id' => $team->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.results.rating', ['category_id' => $category->id]))
            ->assertOk()
            ->assertSee('STC Rating');

        $this->actingAs($admin)
            ->get(route('admin.results.team', ['category_id' => $category->id, 'round' => 'Fecha 1']))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.results.team.approve'), [
                'category_id' => $category->id,
                'round' => 'Fecha 1',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.communications.plaques.generate'), [
                'kind' => 'resultado',
                'match_id' => $match->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('content_posts', [
            'type' => 'plaque',
        ]);
    }

    public function test_sheet_pdf_and_notification_mail(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $sheet = \App\Models\MatchSheet::query()->firstOrFail();
        $draft = AppNotification::query()->where('status', 'draft')->first()
            ?? AppNotification::query()->where('status', 'scheduled')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.sheets.pdf', $sheet))
            ->assertOk()
            ->assertSee('Planilla oficial');

        $this->actingAs($admin)
            ->post(route('admin.communications.notifications.send', $draft))
            ->assertRedirect();

        $this->assertSame('sent', $draft->fresh()->status);
    }

    public function test_knockout_penalties_reopen_lineups_and_officials(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $semi = FixtureMatch::query()->where('stage', 'Final Oro')->firstOrFail();
        $next = FixtureMatch::query()
            ->where('category_id', $semi->category_id)
            ->where('status', 'scheduled')
            ->whereKeyNot($semi->id)
            ->firstOrFail();
        $player = Player::query()->where('team_id', $semi->home_team_id)->firstOrFail();

        $semi->update([
            'home_score' => 1,
            'away_score' => 1,
            'status' => 'finished',
            'next_match_id' => $next->id,
            'next_slot' => 'home',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.fixture.penalties.store', $semi), [
                'team_id' => $semi->home_team_id,
                'scored' => '1',
            ])
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('admin.fixture.penalties.store', $semi), [
                'team_id' => $semi->away_team_id,
                'scored' => '0',
            ])
            ->assertRedirect();

        $semi->refresh()->load('penaltyKicks');
        $this->assertSame((int) $semi->home_team_id, (int) $semi->winnerTeamId());
        $this->assertStringContainsString('pen', $semi->scoreLine());
        $this->assertSame((int) $semi->home_team_id, (int) $next->fresh()->home_team_id);

        $this->actingAs($admin)
            ->get(route('admin.results.brackets', ['category_id' => $semi->category_id]))
            ->assertOk()
            ->assertSee('Ganador');

        $this->actingAs($admin)
            ->patch(route('admin.fixture.reopen', $semi), ['reason' => 'Error de carga en el marcador'])
            ->assertRedirect();

        $semi->refresh();
        $this->assertSame('reopened', $semi->status);
        $this->assertFalse((bool) $semi->published);
        $this->assertFalse((bool) $semi->sheet?->locked);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'reopen',
            'auditable_id' => $semi->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.fixture.status', $semi), ['status' => 'paused'])
            ->assertRedirect();
        $this->assertSame('paused', $semi->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.fixture.lineups.store', $semi), [
                'starters' => [$player->id],
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('match_lineups', [
            'match_id' => $semi->id,
            'player_id' => $player->id,
            'starter' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.fixture.officials'))
            ->assertOk()
            ->assertSee('Agenda de árbitros')
            ->assertSee('Martín Sosa');
    }

    public function test_credentials_requirements_fair_play_slots_team_replace_and_push(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $referee = User::where('email', 'arbitro@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $delegation = \App\Models\Delegation::query()->where('tournament_id', $category->tournament_id)->firstOrFail();
        $field = \App\Models\Field::query()->where('name', 'Cancha 3')->firstOrFail();
        $field->load('venue');

        $this->actingAs($admin)
            ->get(route('admin.users.credential', $referee))
            ->assertOk()
            ->assertSee('Asistente/árbitro')
            ->assertSee('Credencial');

        $this->actingAs($admin)
            ->get(route('admin.delegations.credential', $delegation))
            ->assertOk()
            ->assertSee('Delegado');

        $this->actingAs($admin)
            ->post(route('admin.documents.requirements.store'), [
                'tournament_id' => $category->tournament_id,
                'type' => 'Pasaporte',
                'required' => '1',
            ])
            ->assertRedirect();

        $requirement = DocumentRequirement::query()->where('type', 'Pasaporte')->firstOrFail();
        $this->actingAs($admin)
            ->put(route('admin.documents.requirements.update', $requirement), [
                'tournament_id' => $category->tournament_id,
                'type' => 'Pasaporte internacional',
                'required' => '1',
                'has_expiration' => '1',
                'validity_days' => 180,
                'notes' => 'Vence a los 6 meses',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('document_requirements', [
            'id' => $requirement->id,
            'type' => 'Pasaporte internacional',
            'validity_days' => 180,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.results.fairplay.update'), [
                'category_id' => $category->id,
                'fair_play_yellow' => 2,
                'fair_play_red' => 4,
                'fair_play_incident' => 1,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'fair_play_yellow' => 2,
            'fair_play_red' => 4,
            'fair_play_incident' => 1,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.fields.update', $field), [
                'venue_id' => $field->venue_id,
                'tournament_id' => $field->venue->tournament_id,
                'venue_name' => $field->venue->name,
                'city' => $field->venue->city ?: 'Santa Teresita',
                'address' => $field->venue->address,
                'venue_status' => $field->venue->status ?: 'active',
                'name' => $field->name,
                'surface' => $field->surface ?: 'Sintético',
                'modality' => $field->modality ?: 'Fútbol 7',
                'opens_at' => '08:00',
                'closes_at' => '22:00',
                'status' => 'blocked',
                'lighting' => 1,
                'slots' => [
                    1 => ['opens_at' => '09:00', 'closes_at' => '18:00'],
                    7 => ['opens_at' => '10:00', 'closes_at' => '14:00', 'closed' => '1'],
                ],
            ])
            ->assertRedirect();
        $this->assertSame('blocked', $field->fresh()->status);
        $monday = \App\Models\FieldTimeSlot::query()->where('field_id', $field->id)->where('weekday', 1)->firstOrFail();
        $this->assertStringStartsWith('09:00', (string) $monday->opens_at);
        $sunday = \App\Models\FieldTimeSlot::query()->where('field_id', $field->id)->where('weekday', 7)->firstOrFail();
        $this->assertTrue((bool) $sunday->closed);

        $this->actingAs($admin)
            ->post(route('admin.results.team.approve'), [
                'category_id' => $category->id,
                'round' => 'Finales',
            ])
            ->assertRedirect();

        $selection = \App\Models\RoundSelection::query()
            ->where('category_id', $category->id)
            ->where('round', 'Finales')
            ->firstOrFail();
        $other = Player::query()
            ->whereHas('team', fn ($query) => $query->where('category_id', $category->id))
            ->whereKeyNot($selection->player_id)
            ->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.results.team.replace', $selection), [
                'category_id' => $category->id,
                'round' => 'Finales',
                'player_id' => $other->id,
            ])
            ->assertRedirect();
        $this->assertSame($other->id, $selection->fresh()->player_id);

        $push = AppNotification::query()->where('channel', 'push')->where('status', 'scheduled')->firstOrFail();
        $this->actingAs($admin)
            ->post(route('admin.communications.notifications.send', $push))
            ->assertRedirect();
        $this->assertDatabaseHas('push_messages', [
            'app_notification_id' => $push->id,
            'title' => $push->title,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.communications.notifications.edit', AppNotification::query()->where('status', 'draft')->firstOrFail()))
            ->assertOk()
            ->assertSee('Guardar');
    }
}
