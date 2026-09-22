<?php

namespace Tests\Feature;

use App\Mail\DelegateWelcomeMail;
use App\Models\Category;
use App\Models\Delegation;
use App\Models\Field;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkspaceOperationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_gate_and_category_workspace_render(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $lucia = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $floripa = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $floripaCategory = Category::create([
            'tournament_id' => $floripa->id,
            'name' => 'Sub 13 Floripa Operación',
            'birth_year' => '2013',
            'format' => 'Fútbol 11',
            'modality' => 'Fútbol 11',
            'branch' => 'Masculina',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('workspace.home'))
            ->assertOk()
            ->assertSee('Elegí un torneo')
            ->assertSee('Santa Teresita Cup 2026')
            ->assertSee('STC Buenos Aires 2026')
            ->assertDontSee('STC Floripa')
            ->assertDontSee('Copa Invierno')
            ->assertDontSee('Liga Kids')
            ->assertDontSee('De este torneo');

        $this->actingAs($lucia)
            ->get(route('workspace.home'))
            ->assertOk()
            ->assertSee('Elegí un torneo')
            ->assertSee('Santa Teresita Cup 2026')
            ->assertDontSee('STC Buenos Aires 2026');

        $this->actingAs($lucia)
            ->get(route('workspace.tournaments.show', $category->tournament))
            ->assertOk()
            ->assertSee('Sub 12 Masculino')
            ->assertSee('jugadores')
            ->assertSee('habilitados')
            ->assertSee('fichas pendientes')
            ->assertDontSee('Sub 13 Floripa Operación');

        $this->actingAs($lucia)
            ->get(route('workspace.categories.home', $floripaCategory))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('workspace.categories.home', $category))
            ->assertOk()
            ->assertSee('Sub 12 Masculino')
            ->assertSee('Inicio')
            ->assertSee('Clasificación')
            ->assertSee('Fixture')
            ->assertSee('Configuración')
            ->assertSee('Categorías')
            ->assertSee('Torneos')
            ->assertSee('San Lorenzo')
            ->assertSee('Acerca de')
            ->assertSee('Información')
            ->assertSee('Equipos')
            ->assertSee('ws-team-shield', false)
            ->assertSee(route('workspace.categories.teams.show', [$category, $category->teams()->where('name', 'San Lorenzo')->firstOrFail()], false))
            ->assertSee(route('workspace.categories.fixture', $category, false))
            ->assertDontSee('Admin Web')
            ->assertDontSee('Usuarios y Roles')
            ->assertDontSee('Auditoría');

        $sanLorenzo = $category->teams()->where('name', 'San Lorenzo')->firstOrFail();
        $this->assertSame('A', $sanLorenzo->group_name);

        $this->actingAs($admin)
            ->postJson(route('workspace.categories.teams.group', $category), [
                'team_id' => $sanLorenzo->id,
                'group_name' => 'B',
            ])
            ->assertOk();

        $this->assertSame('B', $sanLorenzo->fresh()->group_name);

        $this->actingAs($admin)
            ->get(route('workspace.categories.standings', $category))
            ->assertOk()
            ->assertSee('Juegos')
            ->assertSee('Agregar partido')
            ->assertSee('Generar partidos')
            ->assertSee('Gestionar categoría')
            ->assertSee('1º Fase')
            ->assertSee('Fecha 1')
            ->assertSee('ws-match-round', false)
            ->assertDontSee('ws-juegos-date', false)
            ->assertSee('type="date"', false)
            ->assertSee('type="time"', false)
            ->assertDontSee('datetime-local')
            ->assertSee('ws-datetime-date', false)
            ->assertSee('Octavos de final')
            ->assertSee('Editar fases')
            ->assertSee('Editar fechas')
            ->assertSee('match-status', false)
            ->assertDontSee('Validado')
            ->assertDontSee('12/08/2026');

        $this->actingAs($admin)
            ->get(route('workspace.categories.settings', $category))
            ->assertOk()
            ->assertSee('Campeonato')
            ->assertSee('Datos básicos')
            ->assertSee('Ajustes deportivos')
            ->assertSee('Personas con acceso')
            ->assertSee('Delegaciones')
            ->assertDontSee('Admin Web');
    }

    public function test_category_directory_groups_the_same_category_across_tournaments(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $lucia = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $floripa = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();

        Category::create([
            'tournament_id' => $floripa->id,
            'name' => 'Sub 12 Masculino',
            'birth_year' => '2014',
            'format' => 'Fútbol 11',
            'modality' => 'Fútbol 11',
            'branch' => 'Masculina',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('workspace.categories.directory'))
            ->assertOk()
            ->assertSee('Categorías')
            ->assertSee('La misma categoría en todos los torneos')
            ->assertSee('Sub 12 Masculino')
            ->assertSee('Santa Teresita Cup 2026')
            ->assertSee('STC Buenos Aires 2026')
            ->assertSee('2 torneos');

        $this->actingAs($lucia)
            ->get(route('workspace.categories.directory'))
            ->assertOk()
            ->assertSee('Sub 12 Masculino')
            ->assertSee('Santa Teresita Cup 2026')
            ->assertDontSee('STC Buenos Aires 2026');
    }

    public function test_tournament_categories_are_scoped_filterable_and_reorderable(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $lucia = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $buenosAires = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $santaTeresita = Tournament::where('slug', 'santa-teresita-cup-2026')->firstOrFail();

        Category::create([
            'tournament_id' => $buenosAires->id,
            'name' => 'Zeta BA',
            'birth_year' => '2010',
            'format' => 'Fútbol 11',
            'modality' => 'Fútbol 11',
            'branch' => 'Masculina',
            'status' => 'active',
            'sort_order' => 0,
        ]);
        Category::create([
            'tournament_id' => $buenosAires->id,
            'name' => 'Alfa BA',
            'birth_year' => '2011',
            'format' => 'Fútbol 11',
            'modality' => 'Fútbol 11',
            'branch' => 'Masculina',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('workspace.home'))
            ->assertOk()
            ->assertSee('Elegí un torneo')
            ->assertDontSee('Todas las del sistema')
            ->assertDontSee('De este torneo');

        $this->actingAs($admin)
            ->get(route('workspace.tournaments.show', $buenosAires))
            ->assertOk()
            ->assertSee('Zeta BA')
            ->assertSee('Alfa BA')
            ->assertSee('STC Buenos Aires 2026')
            ->assertSee('Delegaciones')
            ->assertSee(route('workspace.tournaments.clubs', $buenosAires), false)
            ->assertSee('Buscar')
            ->assertSee('Ordenar A–Z')
            ->assertSee('Arrastrá')
            ->assertSee('Editar')
            ->assertSee('Eliminar categoría')
            ->assertSee(route('workspace.tournaments.show', $buenosAires), false)
            ->assertDontSee('data-name="Sub 12 Masculino"', false)
            ->assertDontSee('La misma en todos los torneos');

        $this->actingAs($admin)
            ->get(route('workspace.categories.home', Category::where('name', 'Sub 12 Masculino')->firstOrFail()))
            ->assertOk()
            ->assertSee('Todas las categorías')
            ->assertSee('Santa Teresita Cup 2026')
            ->assertSee(route('workspace.tournaments.show', $santaTeresita), false);

        $ids = Category::query()
            ->where('tournament_id', $buenosAires->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id');

        $this->actingAs($admin)
            ->patchJson(route('workspace.tournaments.categories.reorder', $buenosAires), [
                'ids' => $ids->reverse()->values()->all(),
            ])
            ->assertOk();

        $this->assertSame(
            $ids->reverse()->values()->all(),
            Category::query()->where('tournament_id', $buenosAires->id)->orderBy('sort_order')->pluck('id')->all()
        );

        $this->actingAs($lucia)
            ->get(route('workspace.tournaments.show', $santaTeresita))
            ->assertOk()
            ->assertSee('Santa Teresita Cup 2026')
            ->assertSee('Sub 12 Masculino')
            ->assertDontSee('Zeta BA');

        $this->actingAs($admin)
            ->get(route('workspace.tournaments.clubs', $buenosAires))
            ->assertOk()
            ->assertSee('Delegaciones')
            ->assertSee('Clubes de este torneo')
            ->assertSee('Añadir club')
            ->assertSee('← Categorías');

        $this->actingAs($admin)
            ->post(route('workspace.tournaments.clubs.store', $buenosAires), [
                'name' => 'Club BA Demo',
                'country_code' => 'AR',
            ])
            ->assertRedirect(route('workspace.tournaments.clubs', $buenosAires));

        $this->assertDatabaseHas('delegations', [
            'tournament_id' => $buenosAires->id,
            'name' => 'Club BA Demo',
        ]);

        $clubBa = Delegation::query()
            ->where('tournament_id', $buenosAires->id)
            ->where('name', 'Club BA Demo')
            ->firstOrFail();
        $zeta = Category::query()->where('name', 'Zeta BA')->firstOrFail();
        $alfa = Category::query()->where('name', 'Alfa BA')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.tournaments.clubs', $buenosAires))
            ->assertOk()
            ->assertSee('Añadir equipo')
            ->assertSee('Equipo en una categoría')
            ->assertSee('Zeta BA')
            ->assertSee('Alfa BA');

        $this->actingAs($admin)
            ->post(route('workspace.tournaments.teams.store', $buenosAires), [
                'delegation_id' => $clubBa->id,
                'category_id' => $zeta->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('teams', [
            'tournament_id' => $buenosAires->id,
            'category_id' => $zeta->id,
            'delegation_id' => $clubBa->id,
            'name' => 'Club BA Demo',
        ]);

        $this->actingAs($admin)
            ->post(route('workspace.tournaments.teams.store', $buenosAires), [
                'delegation_id' => $clubBa->id,
                'category_id' => $alfa->id,
                'name' => 'Club BA Demo',
            ])
            ->assertRedirect();

        $this->assertSame(2, Team::query()->where('delegation_id', $clubBa->id)->count());

        $this->actingAs($lucia)
            ->post(route('workspace.tournaments.teams.store', $buenosAires), [
                'delegation_id' => $clubBa->id,
                'category_id' => $zeta->id,
                'name' => 'No debe entrar',
            ])
            ->assertForbidden();

        $this->actingAs($lucia)
            ->get(route('workspace.tournaments.clubs', $buenosAires))
            ->assertForbidden();

        $this->actingAs($lucia)
            ->get(route('workspace.tournaments.clubs', $santaTeresita))
            ->assertOk()
            ->assertSee('Delegaciones');
    }

    public function test_operation_can_edit_and_delete_a_category_from_the_board(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $lucia = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $buenosAires = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();

        $category = Category::create([
            'tournament_id' => $buenosAires->id,
            'name' => 'Categoria Borrar BA',
            'birth_year' => '2014',
            'format' => 'Fútbol 11',
            'modality' => 'Fútbol 11',
            'branch' => 'Masculina',
            'status' => 'active',
            'competition_format' => 'Grupos y finales',
            'groups_count' => 2,
            'points_win' => 3,
            'points_draw' => 1,
            'points_loss' => 0,
        ]);

        $team = Team::create([
            'tournament_id' => $buenosAires->id,
            'category_id' => $category->id,
            'name' => 'Equipo Borrar BA',
            'delegation_name' => 'Equipo Borrar BA',
            'group_name' => 'A',
            'status' => 'approved',
            'player_capacity' => 18,
        ]);

        $this->actingAs($lucia)
            ->patch(route('workspace.tournaments.categories.update', [$buenosAires, $category]), [
                'name' => 'No debería',
                'birth_year' => '2014',
                'branch' => 'Masculina',
                'modality' => 'Fútbol 11',
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('workspace.tournaments.categories.update', [$buenosAires, $category]), [
                'name' => 'Categoria Editada BA',
                'birth_year' => '2015',
                'branch' => 'Mixta',
                'modality' => 'Fútbol 8',
                'competition_format' => 'Liga',
                'groups_count' => 3,
                'status' => 'inactive',
                'points_win' => 2,
                'points_draw' => 1,
                'points_loss' => 0,
                'description' => 'Editada desde el listado',
            ])
            ->assertRedirect(route('workspace.tournaments.show', $buenosAires));

        $category->refresh();
        $this->assertSame('Categoria Editada BA', $category->name);
        $this->assertSame('2015', $category->birth_year);
        $this->assertSame('Mixta', $category->branch);
        $this->assertSame('Fútbol 8', $category->modality);
        $this->assertSame('inactive', $category->status);
        $this->assertSame('Editada desde el listado', $category->workspaceValue('description'));

        $this->actingAs($lucia)
            ->delete(route('workspace.tournaments.categories.destroy', [$buenosAires, $category]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('workspace.tournaments.categories.destroy', [$buenosAires, $category]))
            ->assertRedirect(route('workspace.tournaments.show', $buenosAires));

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    public function test_operation_can_add_team_player_and_save_settings(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Categoría 2005/2006')->firstOrFail();
        $field = Field::query()->accessibleTo($admin)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('workspace.categories.teams.store', $category), [
                'name' => 'CADU Operación',
                'group_name' => 'A',
            ])
            ->assertRedirect();

        $team = Team::where('name', 'CADU Operación')->firstOrFail();
        $this->assertSame($category->id, $team->category_id);

        $this->actingAs($admin)
            ->post(route('workspace.categories.players.store', $category), [
                'team_id' => $team->id,
                'name' => 'Gomez Juan',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('players', [
            'team_id' => $team->id,
            'last_name' => 'Gomez',
            'first_name' => 'Juan',
            'status' => 'pending',
        ]);

        $other = Team::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'name' => 'Visitante Operación',
            'delegation_name' => 'Visitante Operación',
            'status' => 'approved',
            'player_capacity' => 18,
        ]);

        $this->actingAs($admin)
            ->post(route('workspace.categories.matches.store', $category), [
                'home_team_id' => $team->id,
                'away_team_id' => $other->id,
                'field_id' => $field->id,
                'scheduled_at_date' => '2026-12-16',
                'scheduled_at_time' => '13:40',
                'stage' => '1º Fase',
                'round' => '1º Fecha',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('matches', [
            'category_id' => $category->id,
            'home_team_id' => $team->id,
            'away_team_id' => $other->id,
            'stage' => '1º Fase',
            'scheduled_at' => '2026-12-16 13:40:00',
        ]);

        $this->actingAs($admin)
            ->post(route('workspace.categories.settings.update', $category), [
                'section' => 'prizes',
                'first' => 'Copa de oro',
                'second' => 'Copa de plata',
                'third' => 'Copa de bronce',
                'other' => 'Fair Play',
            ])
            ->assertRedirect();

        $category->refresh();
        $this->assertSame('Copa de oro', $category->workspace()['prizes']['first']);

        $this->actingAs($admin)
            ->post(route('workspace.categories.settings.update', $category), [
                'section' => 'rounds',
                'rounds' => [
                    ['old' => 'Fecha 1', 'name' => 'Fecha 1'],
                    ['old' => 'Fecha 2', 'name' => 'Jornada 2'],
                    ['name' => 'Octavos de final'],
                ],
            ])
            ->assertRedirect();

        $this->assertContains('Jornada 2', $category->fresh()->workspace()['rounds']);

        $this->actingAs($admin)
            ->get(route('workspace.categories.teams.show', [$category, $team]))
            ->assertOk()
            ->assertSee('Eliminar equipo');

        $this->actingAs($admin)
            ->from(route('workspace.categories.teams', $category))
            ->delete(route('workspace.categories.teams.destroy', [$category, $other]))
            ->assertRedirect(route('workspace.categories.teams', $category));

        $this->assertDatabaseMissing('teams', ['id' => $other->id]);
        $this->assertDatabaseHas('teams', ['id' => $team->id]);

        $fromFicha = Team::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'name' => 'Ficha a borrar',
            'delegation_name' => 'Ficha a borrar',
            'status' => 'approved',
            'player_capacity' => 18,
        ]);

        $this->actingAs($admin)
            ->from(route('workspace.categories.teams.show', [$category, $fromFicha]))
            ->delete(route('workspace.categories.teams.destroy', [$category, $fromFicha]))
            ->assertRedirect(route('workspace.categories.teams', $category));
    }

    public function test_operation_can_crud_players_from_team_roster(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Categoría 2009')->firstOrFail();
        $team = Team::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'name' => 'Plantel CRUD Demo',
            'delegation_name' => 'Plantel CRUD Demo',
            'status' => 'approved',
            'player_capacity' => 18,
        ]);

        $this->actingAs($admin)
            ->get(route('workspace.categories.teams.show', [$category, $team]))
            ->assertOk()
            ->assertSee('Plantel')
            ->assertSee('Apellido')
            ->assertSee('Añadir')
            ->assertSee('Todavía no hay jugadores en este plantel.')
            ->assertSee('data-ws-edit-player-form', false);

        $this->actingAs($admin)
            ->post(route('workspace.categories.players.store', $category), [
                'team_id' => $team->id,
                'last_name' => 'Ramirez',
                'first_name' => 'Lucio',
                'document_number' => '48111222',
                'guardian_name' => 'Marta Ramírez',
                'guardian_phone' => '1155559999',
                'guardian_relationship' => 'Madre',
            ])
            ->assertRedirect();

        $player = Player::where('team_id', $team->id)->where('last_name', 'Ramirez')->firstOrFail();
        $this->assertSame('Lucio', $player->first_name);
        $this->assertSame('48111222', $player->document_number);
        $this->assertNull($player->jersey_number);
        $this->assertNull($player->kit_size);
        $this->assertNull($player->position);
        $this->assertNull($player->birth_date);
        $this->assertSame('Marta Ramírez', $player->guardian?->name);
        $this->assertSame('Madre', $player->guardian?->relationship);
        $this->assertSame('awaiting_guardian', $player->status);
        $this->assertTrue($player->documents()->exists());

        $this->actingAs($admin)
            ->get(route('workspace.categories.teams.show', [$category, $team]))
            ->assertOk()
            ->assertSee('RAMIREZ Lucio')
            ->assertSee('Editar')
            ->assertSee('Eliminar')
            ->assertDontSee('Todavía no hay jugadores en este plantel.');

        $this->actingAs($admin)
            ->patch(route('workspace.categories.players.update', [$category, $player]), [
                'first_name' => 'Lucio',
                'last_name' => 'Ramírez',
                'document_number' => '48111222',
                'birth_date' => '2014-03-12',
                'nationality' => 'Argentina',
                'address' => 'Av. Colón 1234, Mar del Plata',
                'position' => 'Delantero',
                'jersey_number' => 10,
                'kit_size' => 'M',
                'preferred_foot' => 'Derecha',
                'height' => '1.62 m',
                'weight' => '52 kg',
                'blood_type' => 'O+',
                'medical_coverage' => 'OSDE',
                'allergies' => 'Ninguna',
                'vaccination_calendar_complete' => '1',
                'ongoing_treatment' => '1',
                'ongoing_treatment_notes' => 'Fisioterapia por esguince de tobillo.',
                'status' => 'enabled',
                'guardian_name' => 'Marta Ramírez',
                'guardian_relationship' => 'Madre',
                'guardian_phone' => '2234000000',
                'guardian_alternate_contact' => 'Carlos Ramírez · 2234000001',
                'consent_status' => 'approved',
            ])
            ->assertRedirect();

        $player->refresh();
        $this->assertSame('Ramírez', $player->last_name);
        $this->assertEquals(10, $player->jersey_number);
        $this->assertSame('M', $player->kit_size);
        $this->assertSame('enabled', $player->status);
        $this->assertSame('Delantero', $player->position);
        $this->assertSame('O+', $player->blood_type);
        $this->assertTrue($player->vaccination_calendar_complete);
        $this->assertTrue($player->ongoing_treatment);
        $this->assertSame('Fisioterapia por esguince de tobillo.', $player->ongoing_treatment_notes);
        $this->assertSame('Av. Colón 1234, Mar del Plata', $player->address);
        $this->assertSame('Marta Ramírez', $player->guardian?->name);
        $this->assertSame('Madre', $player->guardian?->relationship);

        $this->actingAs($admin)
            ->patch(route('workspace.categories.players.update', [$category, $player]), [
                'first_name' => 'Lucio',
                'last_name' => 'Ramírez',
                'document_number' => '48111222',
                'birth_date' => '2014-03-12',
                'jersey_number' => 10,
                'status' => 'enabled',
                'guardian_name' => 'Marta Ramírez',
                'guardian_phone' => '2234000000',
                'consent_status' => 'approved',
            ])
            ->assertRedirect();

        $this->assertSame('M', $player->fresh()->kit_size);

        $this->actingAs($admin)
            ->get(route('workspace.categories.players.show', [$category, $player]))
            ->assertOk()
            ->assertSee('Ver ficha')
            ->assertSee('Editar ficha')
            ->assertSee('Domicilio')
            ->assertSee('Información deportiva')
            ->assertSee('Información médica')
            ->assertSee('Grupo sanguíneo')
            ->assertSee('Vínculo')
            ->assertSee('Contacto de emergencia')
            ->assertDontSee('Guardar ficha');

        $this->actingAs($admin)
            ->get(route('workspace.categories.players.edit', [$category, $player]))
            ->assertOk()
            ->assertSee('Editar ficha')
            ->assertSee('Talle')
            ->assertSee('Numérica')
            ->assertSee('Letras')
            ->assertSee('value="6"', false)
            ->assertSee('value="10"', false)
            ->assertSee('value="14"', false)
            ->assertSee('value="16"', false)
            ->assertSee('value="S"', false)
            ->assertSee('value="L"', false)
            ->assertSee('value="XL"', false)
            ->assertSee('XXL')
            ->assertSee('Siguiente')
            ->assertSee('Finalizar')
            ->assertDontSee('>M</span>', false)
            ->assertDontSee('Guardar ficha');

        $this->actingAs($admin)
            ->from(route('workspace.categories.teams.show', [$category, $team]))
            ->delete(route('workspace.categories.players.destroy', [$category, $player]))
            ->assertRedirect(route('workspace.categories.teams.show', [$category, $team]));

        $this->assertDatabaseMissing('players', ['id' => $player->id]);
    }

    public function test_operation_saves_team_shield_and_country_flag(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Categoría 2009')->firstOrFail();
        $team = $category->teams()->create([
            'tournament_id' => $category->tournament_id,
            'name' => 'Deportivo La Guaira',
            'delegation_name' => 'Deportivo La Guaira',
            'group_name' => 'C',
            'status' => 'approved',
            'player_capacity' => 18,
        ]);

        $this->actingAs($admin)
            ->get(route('workspace.categories.teams.show', [$category, $team]))
            ->assertOk()
            ->assertSee('Escudo')
            ->assertSee('Ajustar')
            ->assertSee('Vista previa')
            ->assertSee('País')
            ->assertSee('Venezuela');

        $this->actingAs($admin)
            ->patch(route('workspace.categories.teams.update', [$category, $team]), [
                'name' => 'Deportivo La Guaira',
                'delegation_name' => 'Deportivo La Guaira',
                'group_name' => 'C',
                'status' => 'approved',
                'country_code' => 'VE',
                'shield_file' => UploadedFile::fake()->image('escudo.png', 80, 80),
            ])
            ->assertRedirect();

        $team->refresh();
        $this->assertSame('VE', $team->country_code);
        $this->assertNotNull($team->shield_path);
        $this->assertTrue(is_file(public_path($team->shield_path)));
        $this->assertSame('Venezuela', $team->countryName());
        $this->assertNotNull($team->flagUrl());
        $this->assertTrue(
            str_contains($team->flagUrl(), 'images/flags/ve.png')
            || str_contains($team->flagUrl(), 'images/flags/ve.svg')
            || str_contains($team->flagUrl(), 'flagcdn.com/w80/ve.png')
            || str_contains($team->flagUrl(), 'flagcdn.com/w40/ve.png')
        );

        $this->actingAs($admin)
            ->get(route('workspace.categories.standings', $category))
            ->assertOk()
            ->assertSee('ws-team-shield', false)
            ->assertSee('ws-flag', false)
            ->assertSee($team->flagUrl(), false);

        File::delete(public_path($team->shield_path));
    }

    public function test_operation_can_change_category_banner_and_pick_a_club_logo(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Categoría 2005/2006')->firstOrFail();
        $otherTournament = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $sibling = Category::create([
            'tournament_id' => $otherTournament->id,
            'name' => $category->name,
            'birth_year' => $category->birth_year,
            'branch' => $category->branch,
            'format' => $category->modality,
            'modality' => $category->modality,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('workspace.categories.settings', $category))
            ->assertOk()
            ->assertSee('Imagen de la categoría')
            ->assertSee('Cambiar')
            ->assertSee('Ajustar')
            ->assertSee('Logos del torneo');

        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $this->actingAs($admin)
            ->patchJson(route('workspace.categories.banner', $category), [
                'shield_data' => $png,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $category->refresh();
        $this->assertNotNull($category->image_path);
        $this->assertTrue(str_starts_with($category->image_path, 'images/categories/'));
        $this->assertTrue(is_file(public_path($category->image_path)));
        $this->assertSame($category->image_path, $sibling->fresh()->image_path);
        $uploaded = $category->image_path;

        File::ensureDirectoryExists(public_path('images/delegations'));
        $clubLogo = 'images/delegations/club-banner-test.png';
        File::copy(public_path('images/stc-logo.png'), public_path($clubLogo));

        $club = Delegation::query()->where('tournament_id', $category->tournament_id)->firstOrFail();
        $club->update(['logo_path' => $clubLogo]);

        $this->actingAs($admin)
            ->patchJson(route('workspace.categories.banner', $category), [
                'image_path' => $clubLogo,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $category->refresh();
        $this->assertSame($clubLogo, $category->image_path);
        $this->assertSame($clubLogo, $sibling->fresh()->image_path);
        $this->assertFalse(is_file(public_path($uploaded)));

        $this->actingAs($admin)
            ->patch(route('workspace.tournaments.categories.update', [$category->tournament, $category]), [
                'name' => $category->name,
                'birth_year' => $category->birth_year,
                'branch' => $category->branch,
                'modality' => $category->modality,
                'competition_format' => $category->competition_format,
                'groups_count' => $category->groups_count,
                'status' => $category->status,
                'image_path' => 'images/stc-logo.png',
            ])
            ->assertRedirect();

        $this->assertSame('images/stc-logo.png', $category->fresh()->image_path);
        $this->assertSame('images/stc-logo.png', $sibling->fresh()->image_path);

        File::delete(public_path($clubLogo));
    }

    public function test_operation_can_change_tournament_dashboard_image(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $tournament = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('Imagen del torneo')
            ->assertSee('Cambiar')
            ->assertSee('Ajustar');

        $category = $tournament->categories()->first();
        if (! $category) {
            $category = Category::create([
                'tournament_id' => $tournament->id,
                'name' => 'Categoria Default Logo',
                'birth_year' => '2014',
                'format' => 'Fútbol 11',
                'modality' => 'Fútbol 11',
                'branch' => 'Masculina',
                'status' => 'active',
                'image_path' => 'images/stc-logo.png',
            ]);
        } else {
            $category->update(['image_path' => 'images/stc-logo.png']);
        }

        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $this->actingAs($admin)
            ->patchJson(route('workspace.tournaments.banner', $tournament), [
                'shield_data' => $png,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('kind', 'tournament');

        $tournament->refresh();
        $this->assertNotNull($tournament->logo_path);
        $this->assertTrue(str_starts_with($tournament->logo_path, 'images/tournaments/'));
        $this->assertTrue(is_file(public_path($tournament->logo_path)));

        $category->refresh()->load('tournament');
        $this->assertNull($category->image_path);
        $this->assertFalse($category->hasCustomBanner());
        $this->assertSame($tournament->logoUrl(), $category->bannerUrl());

        $this->actingAs($admin)
            ->get(route('dashboard', ['tournament_id' => $tournament->id]))
            ->assertOk()
            ->assertSee($tournament->logoUrl(), false);

        File::delete(public_path($tournament->logo_path));
    }

    public function test_operation_saves_cropped_shield_from_base64(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Categoría 2009')->firstOrFail();
        $team = $category->teams()->create([
            'tournament_id' => $category->tournament_id,
            'name' => 'Escudo Recorte',
            'delegation_name' => 'Escudo Recorte',
            'group_name' => 'C',
            'status' => 'approved',
            'player_capacity' => 18,
        ]);

        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $this->actingAs($admin)
            ->patchJson(route('workspace.categories.teams.shield', [$category, $team]), [
                'shield_data' => $png,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $team->refresh();
        $this->assertNotNull($team->shield_path);
        $this->assertTrue(is_file(public_path($team->shield_path)));
        $this->assertStringEndsWith('.png', $team->shield_path);

        File::delete(public_path($team->shield_path));
    }

    public function test_operation_shares_club_shield_across_teams(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Categoría 2009')->firstOrFail();
        $otherCategory = Category::where('tournament_id', $category->tournament_id)
            ->where('id', '!=', $category->id)
            ->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.categories.teams', $category))
            ->assertOk()
            ->assertSee('Elegí el club')
            ->assertSee('Delegaciones');

        $this->actingAs($admin)
            ->get(route('workspace.categories.clubs', $category))
            ->assertOk()
            ->assertSee('Delegaciones')
            ->assertSee('Añadir club');

        $this->actingAs($admin)
            ->post(route('workspace.categories.teams.store', $category), [
                'name' => 'San Lorenzo Sub 13',
                'delegation_name' => 'San Lorenzo Club',
                'group_name' => 'C',
                'shield_file' => UploadedFile::fake()->image('boedo.png', 80, 80),
            ])
            ->assertRedirect();

        $first = Team::where('name', 'San Lorenzo Sub 13')->firstOrFail();
        $this->assertNotNull($first->delegation_id);
        $this->assertNotNull($first->shield_path);
        $this->assertTrue(is_file(public_path($first->shield_path)));
        $this->assertSame($first->shield_path, $first->delegation->logo_path);

        $this->actingAs($admin)
            ->get(route('workspace.categories.teams.show', [$category, $first]))
            ->assertOk()
            ->assertSee('Escudo del club')
            ->assertSee('Club / delegación')
            ->assertSee($first->delegation->name);

        $this->actingAs($admin)
            ->post(route('workspace.categories.teams.store', $otherCategory), [
                'name' => 'San Lorenzo Sub 14',
                'delegation_id' => $first->delegation_id,
            ])
            ->assertRedirect();

        $second = Team::where('name', 'San Lorenzo Sub 14')->firstOrFail();
        $second->load('delegation');
        $this->assertSame($first->delegation_id, $second->delegation_id);
        $this->assertSame($first->fresh()->shieldUrl(), $second->shieldUrl());

        File::delete(public_path($first->shield_path));
    }

    public function test_operation_can_add_a_club_without_a_team(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Categoría 2009')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('workspace.categories.clubs.store', $category), [
                'name' => 'Club Atlético Demo',
                'country_code' => 'AR',
                'shield_file' => UploadedFile::fake()->image('club.png', 80, 80),
            ])
            ->assertRedirect();

        $club = \App\Models\Delegation::query()
            ->where('tournament_id', $category->tournament_id)
            ->where('name', 'Club Atlético Demo')
            ->firstOrFail();

        $this->assertSame('Argentina', $club->country);
        $this->assertSame('', (string) $club->delegate_name);
        $this->assertNotNull($club->logo_path);
        $this->assertTrue(is_file(public_path($club->logo_path)));

        $this->actingAs($admin)
            ->get(route('workspace.categories.clubs', $category))
            ->assertOk()
            ->assertSee('Club Atlético Demo')
            ->assertSee('Editar')
            ->assertSee('Eliminar');

        $this->actingAs($admin)
            ->get(route('workspace.categories.clubs.show', [$category, $club]))
            ->assertOk()
            ->assertSee('Responsable')
            ->assertSee('Delegado')
            ->assertSee('Equipos');

        $this->actingAs($admin)
            ->patch(route('workspace.categories.clubs.update', [$category, $club]), [
                'name' => 'Club Atlético Demo',
                'country_code' => 'AR',
                'delegate_name' => 'Juan Delegado',
                'delegate_email' => 'juan.delegado@club.test',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('delegations', [
            'id' => $club->id,
            'delegate_name' => 'Juan Delegado',
            'delegate_email' => 'juan.delegado@club.test',
        ]);

        $delegateUser = User::where('email', 'delegado@stctorneos.demo')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('workspace.categories.clubs.update', [$category, $club]), [
                'name' => 'Club Atlético Demo',
                'country_code' => 'AR',
                'delegate_user_id' => $delegateUser->id,
            ])
            ->assertRedirect();

        $this->assertSame('Sebastián Martínez', $club->fresh()->delegate_name);
        $this->assertSame($delegateUser->email, $club->fresh()->delegate_email);
        $this->assertSame($club->id, $delegateUser->fresh()->delegation_id);

        $orphan = Team::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'name' => 'Club Atlético Demo',
            'delegation_name' => 'Club Atlético Demo',
            'group_name' => 'C',
            'status' => 'approved',
            'player_capacity' => 18,
        ]);

        $this->actingAs($admin)
            ->get(route('workspace.categories.clubs', $category))
            ->assertOk();

        $orphan->refresh();
        $this->assertSame($club->id, $orphan->delegation_id);

        $this->actingAs($admin)
            ->delete(route('workspace.categories.clubs.destroy', [$category, $club]))
            ->assertRedirect(route('workspace.categories.clubs', $category));

        $this->assertDatabaseMissing('delegations', ['id' => $club->id]);
        $this->assertDatabaseHas('teams', ['id' => $orphan->id, 'delegation_id' => null]);

        File::delete(public_path($club->logo_path));
    }

    public function test_new_club_reuses_known_shield_from_another_tournament(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $sourceTournament = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $targetTournament = Tournament::create([
            'name' => 'Copa Escudo Reutilizado',
            'slug' => 'copa-escudo-reutilizado',
            'edition' => 'Demo',
            'country' => 'Argentina',
            'city' => 'Mar del Plata',
            'venue_name' => 'Predio Test',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'location' => 'Mar del Plata, Argentina',
            'starts_at' => '2026-11-01',
            'ends_at' => '2026-11-03',
            'status' => 'draft',
            'visibility' => 'private',
            'contact_name' => 'Mesa',
            'contact_email' => 'mesa@stc.demo',
            'description' => 'Torneo de prueba',
        ]);

        $this->actingAs($admin)
            ->post(route('workspace.tournaments.clubs.store', $sourceTournament), [
                'name' => 'Deportivo Pinamar',
                'country_code' => 'AR',
                'shield_file' => UploadedFile::fake()->image('pinamar.png', 80, 80),
            ])
            ->assertRedirect();

        $sourceClub = Delegation::query()
            ->where('tournament_id', $sourceTournament->id)
            ->where('name', 'Deportivo Pinamar')
            ->firstOrFail();

        $this->assertTrue($sourceClub->hasCustomLogo());

        $this->actingAs($admin)
            ->getJson(route('workspace.clubs.known-logo', ['name' => 'Deportivo Pinamar']))
            ->assertOk()
            ->assertJson([
                'found' => true,
            ]);

        $this->actingAs($admin)
            ->post(route('workspace.tournaments.clubs.store', $targetTournament), [
                'name' => 'Deportivo Pinamar',
                'country_code' => 'AR',
            ])
            ->assertRedirect();

        $targetClub = Delegation::query()
            ->where('tournament_id', $targetTournament->id)
            ->where('name', 'Deportivo Pinamar')
            ->firstOrFail();

        $this->assertSame($sourceClub->logo_path, $targetClub->logo_path);
        $this->assertTrue($targetClub->hasCustomLogo());

        File::delete(public_path($sourceClub->logo_path));
    }

    public function test_operation_can_create_delegate_from_club_and_scope_their_view(): void
    {
        Mail::fake();

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $lucia = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Categoría 2009')->firstOrFail();
        $club = \App\Models\Delegation::query()
            ->where('tournament_id', $category->tournament_id)
            ->where('name', 'San Lorenzo')
            ->firstOrFail();
        $other = \App\Models\Delegation::query()
            ->where('tournament_id', $category->tournament_id)
            ->where('name', 'Pampero')
            ->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.categories.clubs.show', [$category, $club]))
            ->assertOk()
            ->assertSee('Nuevo')
            ->assertSee('Crear y asignar')
            ->assertSee('Agregar delegado');

        $this->actingAs($lucia)
            ->post(route('workspace.categories.clubs.delegate', [$category, $club]), [
                'name' => 'Ana Delegada',
                'email' => 'ana.sanlorenzo@club.test',
                'phone' => '+54 11 4000-1234',
            ])
            ->assertRedirect()
            ->assertSessionHas('delegate_welcome_whatsapp')
            ->assertSessionHas('status');

        Mail::assertSent(DelegateWelcomeMail::class, function (DelegateWelcomeMail $mail) {
            return $mail->hasTo('ana.sanlorenzo@club.test')
                && filled($mail->password)
                && $mail->password !== 'stcdemo'
                && str_contains($mail->messageText, 'Correo: ana.sanlorenzo@club.test')
                && str_contains($mail->messageText, 'Clave: '.$mail->password)
                && str_contains($mail->messageText, route('login', absolute: true));
        });

        $delegate = User::where('email', 'ana.sanlorenzo@club.test')->firstOrFail();
        $this->assertTrue($delegate->hasRole('delegado'));
        $this->assertSame($club->id, $delegate->delegation_id);
        $this->assertSame($category->tournament_id, $delegate->tournament_id);
        $this->assertSame('Ana Delegada', $club->fresh()->delegate_name);
        $this->assertSame('ana.sanlorenzo@club.test', $club->fresh()->delegate_email);
        $this->assertFalse(Hash::check('stcdemo', $delegate->password));

        $sent = Mail::sent(DelegateWelcomeMail::class)->first();
        $this->assertNotNull($sent);
        $this->assertTrue(Hash::check($sent->password, $delegate->password));
        $this->assertStringContainsString('wa.me/541140001234', session('delegate_welcome_whatsapp'));
        $this->assertStringContainsString(rawurlencode('Clave: '.$sent->password), session('delegate_welcome_whatsapp'));

        $this->actingAs($admin)
            ->get(route('workspace.categories.clubs.show', [$category, $club]))
            ->assertOk()
            ->assertSee('Ana Delegada')
            ->assertSee('ana.sanlorenzo@club.test')
            ->assertSee('WhatsApp')
            ->assertSee('wa.me/541140001234', false)
            ->assertSee('Sudamérica');

        $this->actingAs($lucia)
            ->post(route('workspace.categories.clubs.delegate', [$category, $club]), [
                'name' => 'Admin Robado',
                'email' => 'admin@stctorneos.demo',
                'phone' => '+54 11 4000-9999',
            ])
            ->assertSessionHasErrors('email');

        $this->actingAs($delegate)
            ->get(route('workspace.home'))
            ->assertOk()
            ->assertSee('Elegí un torneo')
            ->assertSee('Santa Teresita Cup 2026');

        $this->actingAs($delegate)
            ->get(route('workspace.tournaments.show', $category->tournament))
            ->assertOk()
            ->assertSee('Categoría 2009')
            ->assertSee('Sub 12 Masculino', false)
            ->assertSee('Fútbol 11', false)
            ->assertSee('equipos', false)
            ->assertDontSee('fichas pendientes', false)
            ->assertDontSee('Nueva categoría', false);

        $this->actingAs($delegate)
            ->get(route('workspace.categories.directory'))
            ->assertOk()
            ->assertSee('Sub 12 Masculino')
            ->assertSee('Categoría 2009');

        $foreignCategory = Category::where('name', 'Sub 12 Masculino')->firstOrFail();

        $this->actingAs($delegate)
            ->get(route('workspace.categories.home', $foreignCategory))
            ->assertOk()
            ->assertSee('Sub 12 Masculino')
            ->assertSee('Fútbol', false)
            ->assertSee('Acerca de', false);

        $this->actingAs($delegate)
            ->get(route('workspace.categories.settings', $foreignCategory))
            ->assertForbidden();

        $this->actingAs($delegate)
            ->get(route('workspace.categories.standings', $foreignCategory))
            ->assertOk()
            ->assertSee('Tablas')
            ->assertSee('Cruces')
            ->assertSee('Fair Play')
            ->assertDontSee('data-ws-open="plus-menu"', false);

        $this->actingAs($delegate)
            ->get(route('workspace.categories.brackets', $foreignCategory))
            ->assertOk()
            ->assertSee('Cruces');

        $this->actingAs($delegate)
            ->get(route('workspace.categories.fairplay', $foreignCategory))
            ->assertOk()
            ->assertSee('Fair Play')
            ->assertSee('Disciplina por equipo');

        $this->actingAs($delegate)
            ->get(route('workspace.categories.fixture', $foreignCategory))
            ->assertOk()
            ->assertSee('Fixture publicado')
            ->assertSee('ws-fixture-poster', false)
            ->assertDontSee('Generar partidos', false)
            ->assertDontSee('Agenda de partidos', false);

        $this->actingAs($delegate)
            ->get(route('workspace.tournaments.clubs', $category->tournament))
            ->assertRedirect(route('workspace.my-club', ['tournament' => $category->tournament_id], false));

        $this->actingAs($delegate)
            ->get(route('workspace.tournaments.clubs.show', [$category->tournament, $club]))
            ->assertRedirect(route('workspace.my-club', ['tournament' => $category->tournament_id], false));

        $this->actingAs($delegate)
            ->get(route('workspace.my-club'))
            ->assertOk()
            ->assertSee('Todos los equipos')
            ->assertSee('San Lorenzo');

        $otherTournament = \App\Models\Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $this->actingAs($delegate)
            ->get(route('workspace.tournaments.clubs.show', [$otherTournament, $club]))
            ->assertNotFound();

        $this->actingAs($delegate)
            ->get(route('workspace.tournaments.clubs.show', [$club->tournament_id, $club]))
            ->assertRedirect(route('workspace.my-club', ['tournament' => $club->tournament_id], false));

        $this->actingAs($delegate)
            ->get(route('workspace.tournaments.clubs.show', [$category->tournament, $other]))
            ->assertForbidden();

        $this->actingAs($delegate)
            ->get(route('admin.delegations.index'))
            ->assertRedirect(route('workspace.home'));

        $playCategory = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $pamperoTeam = \App\Models\Team::query()
            ->where('category_id', $playCategory->id)
            ->where('name', 'Pampero')
            ->firstOrFail();

        $this->actingAs($delegate)
            ->get(route('workspace.categories.teams', $playCategory))
            ->assertOk()
            ->assertSee('Pampero', false)
            ->assertSee('San Lorenzo', false);

        $this->actingAs($delegate)
            ->get(route('workspace.categories.teams.show', [$playCategory, $pamperoTeam]))
            ->assertOk()
            ->assertSee('Pampero', false);

        $sanLorenzoTeam = \App\Models\Team::query()
            ->where('category_id', $playCategory->id)
            ->where('name', 'San Lorenzo')
            ->firstOrFail();

        $this->actingAs($delegate)
            ->get(route('workspace.categories.players', $playCategory))
            ->assertRedirect(route('workspace.categories.players', [$playCategory, 'team_id' => $sanLorenzoTeam->id]));

        $foreignPlayer = \App\Models\Player::query()
            ->where('first_name', 'Mateo')
            ->whereHas('team', fn ($query) => $query->where('name', 'Pampero'))
            ->firstOrFail();

        $this->actingAs($delegate)
            ->get(route('workspace.categories.players.show', [$playCategory, $foreignPlayer]))
            ->assertOk()
            ->assertSee('Ficha pública', false)
            ->assertSee('Mediocampista', false)
            ->assertSee('No habilitado', false)
            ->assertDontSee('Documentación', false)
            ->assertDontSee('Información médica', false)
            ->assertDontSee('Tutor', false)
            ->assertDontSee('guardian_name', false);
    }

    public function test_delegate_cannot_edit_roster_when_registrations_are_closed(): void
    {
        $this->seed(DatabaseSeeder::class);

        $delegate = User::where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $team = Team::where('name', 'Leones FC')->where('category_id', $category->id)->firstOrFail();
        $player = Player::where('team_id', $team->id)->firstOrFail();

        \App\Services\RegistrationControl::setCategoryOpen($category, false);

        $this->actingAs($delegate)
            ->get(route('workspace.categories.teams.show', [$category, $team]))
            ->assertOk()
            ->assertSee('cerradas', false);

        $this->actingAs($delegate)
            ->post(route('workspace.categories.players.store', $category), [
                'team_id' => $team->id,
                'first_name' => 'Nuevo',
                'last_name' => 'Jugador',
            ])
            ->assertForbidden();

        $this->actingAs($delegate)
            ->patch(route('workspace.categories.players.update', [$category, $player]), [
                'first_name' => $player->first_name,
                'last_name' => $player->last_name,
                'status' => $player->status,
            ])
            ->assertForbidden();

        \App\Services\RegistrationControl::setCategoryOpen($category, true);

        $this->actingAs($delegate)
            ->get(route('workspace.categories.teams.show', [$category, $team]))
            ->assertOk()
            ->assertDontSee('Las inscripciones están cerradas', false);
    }

    public function test_admin_can_reopen_registrations_after_tournament_period_ends(): void
    {
        $this->seed(DatabaseSeeder::class);

        $delegate = User::where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $team = Team::where('name', 'Leones FC')->where('category_id', $category->id)->firstOrFail();

        $category->tournament->update([
            'registration_starts_at' => now()->subMonths(6),
            'registration_ends_at' => now()->subMonths(2),
        ]);

        $this->assertFalse($category->fresh()->tournamentRegistrationWindowOpen());

        \App\Services\RegistrationControl::setCategoryOpen($category, true);

        $this->actingAs($delegate)
            ->get(route('workspace.categories.teams.show', [$category, $team]))
            ->assertOk()
            ->assertDontSee('período de inscripción del torneo finalizó', false);

        $this->actingAs($delegate)
            ->post(route('workspace.categories.players.store', $category), [
                'team_id' => $team->id,
                'first_name' => 'Reabierto',
                'last_name' => 'Demo',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_delegate_sees_simplified_public_category_cards(): void
    {
        $this->seed(DatabaseSeeder::class);

        $delegate = User::where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $tournament = Tournament::where('slug', 'santa-teresita-cup-2026')->firstOrFail();

        $this->actingAs($delegate)
            ->get(route('workspace.tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('Sub 12 Masculino')
            ->assertSee('is-public', false)
            ->assertSee('Inscripciones abiertas', false)
            ->assertSee('equipos', false)
            ->assertDontSee('habilitados', false)
            ->assertDontSee('fichas pendientes', false)
            ->assertDontSee('docs pendientes', false)
            ->assertDontSee('Jugadores <strong>', false);

        $this->actingAs($delegate)
            ->get(route('workspace.categories.directory'))
            ->assertOk()
            ->assertDontSee(' jug.', false)
            ->assertDontSee('partidos', false);
    }

    public function test_delegate_can_belong_to_clubs_in_multiple_tournaments(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $delegate = User::where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $stc = Tournament::where('slug', 'santa-teresita-cup-2026')->firstOrFail();
        $ba = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $stcCategory = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $baCategory = Category::create([
            'tournament_id' => $ba->id,
            'name' => 'Sub 12 BA Multi',
            'birth_year' => '2014',
            'format' => 'Fútbol 11',
            'modality' => 'Fútbol 11',
            'branch' => 'Masculina',
            'status' => 'active',
        ]);
        $baClub = Delegation::create([
            'tournament_id' => $ba->id,
            'name' => 'Club BA Multi',
            'country' => 'Argentina',
            'delegate_name' => '',
            'status' => 'approved',
        ]);
        Team::create([
            'tournament_id' => $ba->id,
            'category_id' => $baCategory->id,
            'delegation_id' => $baClub->id,
            'delegation_name' => $baClub->name,
            'name' => 'Club BA Multi',
            'group_name' => 'A',
            'status' => 'approved',
            'player_capacity' => 18,
        ]);
        $leones = Delegation::query()
            ->where('tournament_id', $stc->id)
            ->where('name', 'Leones')
            ->firstOrFail();

        $this->assertTrue($delegate->assignedDelegations()->whereKey($leones->id)->exists());

        $this->actingAs($admin)
            ->post(route('workspace.categories.clubs.delegate', [$baCategory, $baClub]), [
                'name' => $delegate->name,
                'email' => $delegate->email,
                'phone' => $delegate->phone ?: '+54 11 4000-5555',
            ])
            ->assertRedirect();

        $delegate->refresh();
        $this->assertTrue($delegate->assignedDelegations()->whereKey($leones->id)->exists());
        $this->assertTrue($delegate->assignedDelegations()->whereKey($baClub->id)->exists());
        $this->assertSame($leones->id, $delegate->scopedDelegationId((int) $stc->id));
        $this->assertSame($baClub->id, $delegate->scopedDelegationId((int) $ba->id));

        $this->actingAs($delegate)
            ->get(route('workspace.my-club'))
            ->assertOk()
            ->assertSee('Todos los equipos')
            ->assertSee('LEONES FC', false)
            ->assertSee('Club BA Multi')
            ->assertSee('Santa Teresita Cup 2026', false)
            ->assertSee('STC Buenos Aires 2026', false);

        $this->actingAs($delegate)
            ->get(route('workspace.tournaments.show', $stc))
            ->assertOk()
            ->assertSee('Sub 12 Masculino');

        $this->actingAs($delegate)
            ->get(route('workspace.tournaments.show', $ba))
            ->assertOk();
    }

    public function test_workspace_can_create_tournament_and_toggle_registrations_globally(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $tournament = Tournament::where('slug', 'santa-teresita-cup-2026')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.home'))
            ->assertOk()
            ->assertSee('Crear torneo', false);

        $this->actingAs($admin)
            ->post(route('workspace.tournaments.store'), [
                'name' => 'Torneo Demo Operación',
                'city' => 'Mar del Plata',
            ])
            ->assertRedirect();

        $created = Tournament::where('name', 'Torneo Demo Operación')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('Abrir todas', false)
            ->assertSee('Cerrar todas', false);

        $this->actingAs($admin)
            ->patch(route('workspace.tournaments.registrations', $tournament), ['open' => 0])
            ->assertRedirect(route('workspace.tournaments.show', $tournament));

        $this->assertFalse($tournament->fresh()->categories->first()->registrationsOpen());

        $this->actingAs($admin)
            ->patch(route('workspace.tournaments.registrations', $tournament), ['open' => 1])
            ->assertRedirect(route('workspace.tournaments.show', $tournament));

        $this->assertTrue($tournament->fresh()->categories->first()->registrationsOpen());
    }

    public function test_operation_can_assign_several_delegates_with_own_login(): void
    {
        Mail::fake();
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $lucia = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Categoría 2009')->firstOrFail();
        $club = Delegation::query()
            ->where('tournament_id', $category->tournament_id)
            ->where('name', 'San Lorenzo')
            ->firstOrFail();
        $other = Delegation::query()
            ->where('tournament_id', $category->tournament_id)
            ->where('name', 'Pampero')
            ->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.categories.clubs.show', [$category, $club]))
            ->assertOk()
            ->assertSee('data-ws-delegate-tags', false)
            ->assertSee('Buscar y agregar');

        $this->actingAs($lucia)
            ->post(route('workspace.categories.clubs.delegate', [$category, $club]), [
                'name' => 'Ana Delegada',
                'email' => 'ana.sanlorenzo@club.test',
                'phone' => '+54 11 4000-1111',
            ])
            ->assertRedirect();

        $this->actingAs($lucia)
            ->post(route('workspace.categories.clubs.delegate', [$category, $club]), [
                'name' => 'Bruno Delegado',
                'email' => 'bruno.sanlorenzo@club.test',
                'phone' => '+54 11 4000-2222',
            ])
            ->assertRedirect();

        $ana = User::where('email', 'ana.sanlorenzo@club.test')->firstOrFail();
        $bruno = User::where('email', 'bruno.sanlorenzo@club.test')->firstOrFail();
        $this->assertSame($club->id, $ana->delegation_id);
        $this->assertSame($club->id, $bruno->delegation_id);
        $this->assertTrue($ana->hasRole('delegado'));
        $this->assertTrue($bruno->hasRole('delegado'));
        $this->assertFalse(Hash::check('stcdemo', $ana->password));
        $this->assertFalse(Hash::check('stcdemo', $bruno->password));
        $this->assertNotSame($ana->password, $bruno->password);

        $anaPassword = collect(Mail::sent(DelegateWelcomeMail::class))
            ->first(fn (DelegateWelcomeMail $mail) => $mail->hasTo('ana.sanlorenzo@club.test'))
            ?->password;
        $brunoPassword = collect(Mail::sent(DelegateWelcomeMail::class))
            ->first(fn (DelegateWelcomeMail $mail) => $mail->hasTo('bruno.sanlorenzo@club.test'))
            ?->password;
        $this->assertNotEmpty($anaPassword);
        $this->assertNotEmpty($brunoPassword);
        $this->assertTrue(Hash::check($anaPassword, $ana->password));
        $this->assertTrue(Hash::check($brunoPassword, $bruno->password));

        $this->actingAs($admin)
            ->get(route('workspace.categories.clubs.show', [$category, $club]))
            ->assertOk()
            ->assertSee('Ana Delegada')
            ->assertSee('Bruno Delegado')
            ->assertSee('ana.sanlorenzo@club.test')
            ->assertSee('bruno.sanlorenzo@club.test');

        $this->actingAs($lucia)
            ->patch(route('workspace.categories.clubs.update', [$category, $club]), [
                'name' => 'San Lorenzo',
                'country_code' => 'AR',
                'sync_delegates' => '1',
                'delegate_user_ids' => [$ana->id, $bruno->id],
            ])
            ->assertRedirect();

        $club->refresh();
        $assigned = $club->credentialDelegates()->pluck('id');
        $this->assertTrue($assigned->contains($ana->id));
        $this->assertTrue($assigned->contains($bruno->id));
        $this->assertSame(2, $assigned->count());

        $this->actingAs($admin)
            ->get(route('workspace.categories.clubs', $category))
            ->assertOk()
            ->assertSee('Delegados: Ana Delegada, Bruno Delegado');

        foreach ([$ana, $bruno] as $delegate) {
            $this->actingAs($delegate)
                ->get(route('workspace.tournaments.clubs', $category->tournament))
                ->assertRedirect(route('workspace.my-club', ['tournament' => $category->tournament_id], false));

            $this->actingAs($delegate)
                ->get(route('workspace.tournaments.clubs.show', [$category->tournament, $club]))
                ->assertRedirect(route('workspace.my-club', ['tournament' => $category->tournament_id], false));

            $this->actingAs($delegate)
                ->get(route('workspace.tournaments.clubs.show', [$category->tournament, $other]))
                ->assertForbidden();
        }

        $this->post(route('logout.demo'));
        $this->post(route('login.perform'), [
            'email' => $ana->email,
            'password' => $anaPassword,
        ])->assertRedirect(route('workspace.home'));
        $this->post(route('logout.demo'));
        $this->post(route('login.perform'), [
            'email' => $bruno->email,
            'password' => $brunoPassword,
        ])->assertRedirect(route('workspace.home'));
    }

    public function test_delegate_can_change_password_from_their_panel(): void
    {
        $this->seed(DatabaseSeeder::class);

        $delegate = User::where('email', 'delegado@stctorneos.demo')->firstOrFail();

        $this->actingAs($delegate)
            ->get(route('workspace.account'))
            ->assertOk()
            ->assertSee('Cambiar clave')
            ->assertSee($delegate->email);

        $this->actingAs($delegate)
            ->patch(route('workspace.account.password'), [
                'current_password' => 'mal',
                'password' => 'claveNueva1',
                'password_confirmation' => 'claveNueva1',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($delegate)
            ->patch(route('workspace.account.password'), [
                'current_password' => 'stcdemo',
                'password' => 'claveNueva1',
                'password_confirmation' => 'claveNueva1',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('claveNueva1', $delegate->fresh()->password));
        $this->assertFalse(Hash::check('stcdemo', $delegate->fresh()->password));

        $this->post(route('logout.demo'));

        $this->post(route('login.perform'), [
            'email' => $delegate->email,
            'password' => 'stcdemo',
        ])->assertSessionHasErrors('email');

        $this->post(route('login.perform'), [
            'email' => $delegate->email,
            'password' => 'claveNueva1',
        ])->assertRedirect(route('workspace.home'));
    }

    public function test_super_admin_can_delete_a_tournament_from_operation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $lucia = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $tournament = Tournament::create([
            'name' => 'Copa Borrar Operación',
            'slug' => 'copa-borrar-operacion',
            'edition' => 'Demo',
            'country' => 'Argentina',
            'city' => 'La Plata',
            'venue_name' => 'Predio Test',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'location' => 'La Plata, Argentina',
            'starts_at' => '2026-10-01',
            'ends_at' => '2026-10-03',
            'status' => 'draft',
            'visibility' => 'private',
            'contact_name' => 'Mesa',
            'contact_email' => 'mesa@stc.demo',
            'description' => 'Torneo de prueba',
        ]);

        $this->actingAs($admin)
            ->get(route('workspace.home'))
            ->assertOk()
            ->assertSee('Eliminar torneo')
            ->assertSee('Copa Borrar Operación');

        $this->actingAs($lucia)
            ->delete(route('workspace.tournaments.destroy', $tournament))
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('workspace.tournaments.destroy', $tournament))
            ->assertRedirect(route('workspace.home'));

        $this->assertDatabaseMissing('tournaments', ['id' => $tournament->id]);
    }

    public function test_empty_tournament_can_create_category_and_enter(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $tournament = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        Category::query()->where('tournament_id', $tournament->id)->delete();

        $this->actingAs($admin)
            ->get(route('workspace.tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('Este torneo todavía no tiene categorías')
            ->assertSee('Crear categoría')
            ->assertSee('Elegí el formato de competencia', false)
            ->assertDontSee('Equipos y jugadores', false)
            ->assertDontSee('Puntos y premios', false);

        $this->actingAs($admin)
            ->post(route('workspace.tournaments.categories.store', $tournament), [
                'name' => 'Categoria 2012',
                'birth_year' => '2012',
                'branch' => 'Masculina',
                'modality' => 'Fútbol 11',
                'competition_format' => 'Grupos y finales',
                'groups_count' => 2,
                'teams' => [
                    ['name' => 'CADU', 'group' => 'A', 'players' => "Gomez Juan\nPerez Lucas"],
                    ['name' => 'Almirante Brown', 'group' => 'A', 'players' => "Diaz Mateo"],
                    ['name' => 'LIFFA', 'group' => 'B', 'players' => "Costa Agustin"],
                ],
            ])
            ->assertRedirect();

        $created = Category::where('name', 'Categoria 2012')->firstOrFail();
        $this->assertSame($tournament->id, $created->tournament_id);
        $this->assertFalse($created->hasCustomBanner());
        $this->assertSame($tournament->fresh()->logoUrl(), $created->bannerUrl());
        $this->assertSame(3, $created->teams()->count());
        $this->assertSame(4, \App\Models\Player::query()->whereHas('team', fn ($query) => $query->where('category_id', $created->id))->count());
        $this->assertGreaterThan(0, $created->matches()->count());

        $this->actingAs($admin)
            ->get(route('workspace.categories.home', $created))
            ->assertOk()
            ->assertSee('Categoria 2012');
    }

    public function test_operation_can_create_category_with_more_than_eight_groups(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $tournament = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('max="'.Category::MAX_GROUPS.'"', false);

        $this->actingAs($admin)
            ->post(route('workspace.tournaments.categories.store', $tournament), [
                'name' => 'Categoria 12 grupos',
                'birth_year' => '2010',
                'branch' => 'Masculina',
                'modality' => 'Fútbol 11',
                'competition_format' => 'Grupos y finales',
                'groups_count' => 12,
                'teams' => [
                    ['name' => 'Norte A', 'group' => 'A', 'players' => "Gomez Juan"],
                    ['name' => 'Sur L', 'group' => 'L', 'players' => "Perez Lucas"],
                ],
            ])
            ->assertRedirect();

        $created = Category::where('name', 'Categoria 12 grupos')->firstOrFail();
        $this->assertSame(12, $created->groups_count);
        $this->assertSame(Category::groupAlphabet(12), $created->groupLetters());
        $this->assertContains('L', $created->groupLetters());

        $this->actingAs($admin)
            ->get(route('workspace.categories.standings', $created))
            ->assertOk()
            ->assertSee('GRUPO L');

        $this->actingAs($admin)
            ->patch(route('workspace.tournaments.categories.update', [$tournament, $created]), [
                'name' => 'Categoria 12 grupos',
                'birth_year' => '2010',
                'branch' => 'Masculina',
                'modality' => 'Fútbol 11',
                'competition_format' => 'Grupos y finales',
                'groups_count' => 16,
                'status' => 'active',
            ])
            ->assertRedirect(route('workspace.tournaments.show', $tournament));

        $this->assertSame(16, $created->fresh()->groups_count);

        $this->actingAs($admin)
            ->post(route('workspace.tournaments.categories.store', $tournament), [
                'name' => 'Categoria demasiados grupos',
                'birth_year' => '2009',
                'branch' => 'Masculina',
                'modality' => 'Fútbol 11',
                'competition_format' => 'Grupos y finales',
                'groups_count' => Category::MAX_GROUPS + 1,
            ])
            ->assertSessionHasErrors('groups_count');
    }

    public function test_operation_covers_player_sheet_docs_and_people(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $team = $category->teams()->firstOrFail();
        $player = $team->players()->firstOrFail();
        $other = $category->teams()->where('id', '!=', $team->id)->firstOrFail();
        $field = Field::query()->whereHas('venue', fn ($query) => $query->where('tournament_id', $category->tournament_id))->firstOrFail();
        $match = \App\Models\FixtureMatch::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'home_team_id' => $team->id,
            'away_team_id' => $other->id,
            'field_id' => $field->id,
            'scheduled_at' => '2026-12-20 16:00:00',
            'stage' => '1º Fase',
            'round' => 'Fecha operación',
            'status' => 'scheduled',
        ]);
        $role = \App\Models\Role::where('slug', 'delegado')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.categories.players.show', [$category, $player]))
            ->assertOk()
            ->assertSee('Ver ficha')
            ->assertSee('Documentación')
            ->assertSee('WhatsApp')
            ->assertDontSee('Admin Web')
            ->assertDontSee('Guardar ficha')
            ->assertDontSee('Generar enlace');

        $this->actingAs($admin)
            ->get(route('workspace.categories.players.edit', [$category, $player]))
            ->assertOk()
            ->assertSee('Editar ficha')
            ->assertSee('Sudamérica')
            ->assertSee('DNI frente')
            ->assertSee('DNI dorso')
            ->assertSee('Cobertura médica')
            ->assertSee('Foto del jugador')
            ->assertSee('Siguiente')
            ->assertSee('Finalizar')
            ->assertDontSee('Confirmación del tutor')
            ->assertDontSee('Reglamento')
            ->assertDontSee('Declaraciones')
            ->assertDontSee('Atención médica');

        $photo = UploadedFile::fake()->image('dni-frente-operacion.jpg', 320, 240);
        $this->actingAs($admin)
            ->post(route('workspace.categories.players.documents', [$category, $player]), [
                'type' => 'DNI frente',
                'file' => $photo,
            ])
            ->assertRedirect();

        $front = $player->fresh(['documents'])->documentByType('DNI frente');
        $this->assertNotNull($front?->file_path);
        $this->assertTrue($front->isImage());

        $this->actingAs($admin)
            ->get(route('workspace.categories.players.edit', [$category, $player]))
            ->assertOk()
            ->assertSee('>Ver</a>', false)
            ->assertSee($front->file_path);

        if ($front->file_path && str_starts_with($front->file_path, 'images/players/docs/')) {
            File::delete(public_path($front->file_path));
        }

        $this->actingAs($admin)
            ->patch(route('workspace.categories.players.update', [$category, $player]), [
                'first_name' => $player->first_name,
                'last_name' => $player->last_name,
                'document_number' => '30111222',
                'status' => 'enabled',
                'guardian_name' => 'Tutor Operación',
                'guardian_phone' => '2235550000',
                'consent_status' => 'approved',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'status' => 'enabled',
            'document_number' => '30111222',
        ]);

        $this->actingAs($admin)
            ->get(route('workspace.categories.teams.show', [$category, $team]))
            ->assertOk()
            ->assertSee('Cuerpo técnico');

        $this->actingAs($admin)
            ->post(route('workspace.categories.teams.staff', [$category, $team]), [
                'first_name' => 'Carlos',
                'last_name' => 'Perez',
                'role' => 'director_tecnico',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('workspace.categories.matches.show', [$category, $match]))
            ->assertOk()
            ->assertSee('Planilla')
            ->assertSee('Guardar horario')
            ->assertSee('Cancha')
            ->assertSee('Día y hora')
            ->assertSee('data-ws-player-filter', false)
            ->assertSee('data-team-id="'.$team->id.'"', false)
            ->assertDontSee('Validado');

        $awayPlayer = $other->players()->first();
        if ($awayPlayer) {
            $this->actingAs($admin)
                ->post(route('workspace.categories.matches.events', [$category, $match]), [
                    'type' => 'goal',
                    'team_id' => $match->home_team_id,
                    'player_id' => $awayPlayer->id,
                    'minute' => 8,
                ])
                ->assertStatus(422);
        }

        $otherField = Field::query()
            ->whereHas('venue', fn ($query) => $query->where('tournament_id', $category->tournament_id))
            ->where('id', '!=', $field->id)
            ->first() ?? $field;

        $this->actingAs($admin)
            ->patch(route('workspace.categories.matches.schedule', [$category, $match]), [
                'field_id' => $otherField->id,
                'scheduled_at' => '2026-12-25 16:30',
            ])
            ->assertRedirect();

        $match->refresh();
        $this->assertSame($otherField->id, $match->field_id);
        $this->assertSame('2026-12-25 16:30:00', $match->scheduled_at->format('Y-m-d H:i:s'));

        $this->actingAs($admin)
            ->patchJson(route('workspace.categories.matches.result', [$category, $match]), [
                'home_score' => 1,
                'away_score' => 0,
                'status' => 'live',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'En juego');

        $this->assertSame('live', $match->fresh()->status);

        $this->actingAs($admin)
            ->get(route('workspace.categories.standings', $category))
            ->assertOk()
            ->assertSee('En juego')
            ->assertDontSee('Validado');

        $this->actingAs($admin)
            ->get(route('workspace.categories.matches.show', [$category, $match]))
            ->assertOk()
            ->assertSee('data-ws-status-banner', false)
            ->assertSee('En juego');

        $this->actingAs($admin)
            ->post(route('workspace.categories.matches.events', [$category, $match]), [
                'type' => 'goal',
                'team_id' => $match->home_team_id,
                'player_id' => $player->id,
                'minute' => 12,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('workspace.categories.matches.close', [$category, $match]))
            ->assertRedirect();

        $this->assertDatabaseHas('matches', [
            'id' => $match->id,
            'status' => 'finished',
        ]);

        $this->actingAs($admin)
            ->get(route('workspace.categories.matches.show', [$category, $match]))
            ->assertOk()
            ->assertSee('Reabrir partido')
            ->assertSee('La planilla está cerrada');

        $this->actingAs($admin)
            ->post(route('workspace.categories.matches.reopen', [$category, $match]))
            ->assertRedirect();

        $this->assertSame('live', $match->fresh()->status);
        $this->assertFalse((bool) $match->fresh()->sheet?->locked);

        $this->actingAs($admin)
            ->post(route('workspace.categories.matches.events', [$category, $match]), [
                'type' => 'goal',
                'team_id' => $match->away_team_id,
                'minute' => 40,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('workspace.categories.documents', $category))
            ->assertOk()
            ->assertSee('Documentación');

        $this->actingAs($admin)
            ->get(route('workspace.categories.inscriptions', $category))
            ->assertOk()
            ->assertSee('Inscripciones');

        $this->actingAs($admin)
            ->post(route('workspace.categories.fields.store', $category), [
                'name' => 'Cancha Operación 7',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fields', ['name' => 'Cancha Operación 7']);

        $this->actingAs($admin)
            ->post(route('workspace.categories.people.store', $category), [
                'name' => 'Delegado Operación',
                'email' => 'delegado.operacion@stctorneos.demo',
                'role_id' => $role->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'delegado.operacion@stctorneos.demo',
            'tournament_id' => $category->tournament_id,
        ]);

        $this->actingAs($admin)
            ->get(route('workspace.categories.settings', $category))
            ->assertOk()
            ->assertSee('Documentación')
            ->assertSee('Personas con acceso')
            ->assertSee('Historial de cambios')
            ->assertDontSee('Admin Web');
    }

    public function test_operation_fixture_stays_inside_the_category(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $lucia = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $floripa = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $floripaCategory = Category::create([
            'tournament_id' => $floripa->id,
            'name' => 'Sub 13 Floripa Fixture',
            'birth_year' => '2013',
            'format' => 'Fútbol 11',
            'modality' => 'Fútbol 11',
            'branch' => 'Masculina',
            'status' => 'active',
        ]);
        $home = $category->teams()->firstOrFail();
        $away = $category->teams()->where('id', '!=', $home->id)->firstOrFail();
        $field = Field::query()->whereHas('venue', fn ($query) => $query->where('tournament_id', $category->tournament_id))->firstOrFail();
        $match = \App\Models\FixtureMatch::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'field_id' => $field->id,
            'scheduled_at' => '2026-12-21 10:00:00',
            'stage' => '1º Fase',
            'round' => 'Fecha fixture operación',
            'status' => 'scheduled',
            'published' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('workspace.categories.fixture', $category))
            ->assertOk()
            ->assertSee('Agenda de partidos')
            ->assertSee('Generar partidos')
            ->assertSee('Mismo grupo')
            ->assertSee('Interzonales')
            ->assertSee('Solo ida')
            ->assertSee('Ida y vuelta')
            ->assertSee('Publicar en app')
            ->assertSee('Agregar partido')
            ->assertSee('Fecha fixture operación')
            ->assertSee('Eliminar partido')
            ->assertDontSee('Admin Web')
            ->assertDontSee('/admin/fixture', false);

        $this->actingAs($lucia)
            ->get(route('workspace.categories.fixture', $category))
            ->assertOk()
            ->assertDontSee('Eliminar partido');

        $this->actingAs($lucia)
            ->delete(route('workspace.categories.matches.destroy', [$category, $match]))
            ->assertForbidden();

        $this->assertDatabaseHas('matches', ['id' => $match->id]);

        $this->actingAs($lucia)
            ->get(route('workspace.categories.fixture', $floripaCategory))
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('workspace.categories.matches.publish', [$category, $match]))
            ->assertRedirect();

        $this->assertTrue($match->fresh()->published);

        $this->actingAs($admin)
            ->patch(route('workspace.categories.matches.observe', [$category, $match]))
            ->assertRedirect();

        $this->assertFalse($match->fresh()->published);

        $fresh = Category::create([
            'tournament_id' => $category->tournament_id,
            'name' => 'Sub 11 Fixture Operación',
            'birth_year' => '2015',
            'format' => 'Fútbol 11',
            'modality' => 'Fútbol 11',
            'branch' => 'Masculina',
            'status' => 'active',
        ]);
        Team::create([
            'tournament_id' => $fresh->tournament_id,
            'category_id' => $fresh->id,
            'name' => 'Local Fixture',
            'delegation_name' => 'Local Fixture',
            'status' => 'approved',
        ]);
        Team::create([
            'tournament_id' => $fresh->tournament_id,
            'category_id' => $fresh->id,
            'name' => 'Visitante Fixture',
            'delegation_name' => 'Visitante Fixture',
            'status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->post(route('workspace.categories.fixture.generate', $fresh), [
                'start_at' => '2026-12-22 09:00',
                'gap_minutes' => 15,
                'priority' => 'field',
                'published' => 0,
            ])
            ->assertRedirect();

        $this->assertGreaterThan(0, \App\Models\FixtureMatch::query()->where('category_id', $fresh->id)->count());

        $grouped = Category::create([
            'tournament_id' => $category->tournament_id,
            'name' => 'Sub 10 Cruces Operación',
            'birth_year' => '2016',
            'format' => 'Fútbol 11',
            'modality' => 'Fútbol 11',
            'branch' => 'Masculina',
            'status' => 'active',
        ]);
        foreach ([['Uno A', 'A'], ['Dos A', 'A'], ['Uno B', 'B'], ['Dos B', 'B']] as [$name, $group]) {
            Team::create([
                'tournament_id' => $grouped->tournament_id,
                'category_id' => $grouped->id,
                'name' => $name,
                'delegation_name' => $name,
                'group_name' => $group,
                'status' => 'approved',
            ]);
        }

        $this->actingAs($admin)
            ->post(route('workspace.categories.fixture.generate', $grouped), [
                'start_at' => '2026-12-23 09:00',
                'gap_minutes' => 15,
                'priority' => 'field',
                'published' => 0,
                'scope' => 'group',
                'legs' => 'ida',
                'stage' => '1º Fase',
            ])
            ->assertRedirect();

        $this->assertSame(2, \App\Models\FixtureMatch::query()->where('category_id', $grouped->id)->count());

        $this->actingAs($admin)
            ->post(route('workspace.categories.fixture.generate', $grouped), [
                'start_at' => '2026-12-24 09:00',
                'gap_minutes' => 15,
                'priority' => 'field',
                'published' => 0,
                'scope' => 'intergroup',
                'legs' => 'ida_vuelta',
                'stage' => '1º Fase',
            ])
            ->assertRedirect();

        $this->assertSame(10, \App\Models\FixtureMatch::query()->where('category_id', $grouped->id)->count());

        $this->actingAs($admin)
            ->patch(route('workspace.categories.fixture.publish', $fresh))
            ->assertRedirect();

        $this->assertTrue(\App\Models\FixtureMatch::query()->where('category_id', $fresh->id)->where('published', true)->exists());

        $toDelete = \App\Models\FixtureMatch::query()->where('category_id', $fresh->id)->firstOrFail();
        $this->actingAs($admin)
            ->from(route('workspace.categories.fixture', $fresh))
            ->delete(route('workspace.categories.matches.destroy', [$fresh, $toDelete]))
            ->assertRedirect(route('workspace.categories.fixture', $fresh));

        $this->assertDatabaseMissing('matches', ['id' => $toDelete->id]);
    }

    public function test_fixture_fills_fecha_one_interzonals_and_can_clear_all_matches(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $base = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $category = Category::create([
            'tournament_id' => $base->tournament_id,
            'name' => 'Sub 11 Interzonales Operación',
            'birth_year' => '2015',
            'format' => 'Fútbol 11',
            'modality' => 'Fútbol 11',
            'branch' => 'Masculina',
            'status' => 'active',
            'groups_count' => 3,
        ]);

        foreach ([
            ['A1', 'A'], ['A2', 'A'], ['A3', 'A'], ['A4', 'A'],
            ['B1', 'B'], ['B2', 'B'], ['B3', 'B'],
            ['C1', 'C'], ['C2', 'C'], ['C3', 'C'],
        ] as [$name, $group]) {
            Team::create([
                'tournament_id' => $category->tournament_id,
                'category_id' => $category->id,
                'name' => $name,
                'delegation_name' => $name,
                'group_name' => $group,
                'status' => 'approved',
            ]);
        }

        $this->actingAs($admin)
            ->get(route('workspace.categories.fixture', $category))
            ->assertOk()
            ->assertSee('Interzonales (entre grupos)')
            ->assertSee('en cada fecha queda 1 libre')
            ->assertSee('Completar cada fecha: el libre de cada grupo juega con el libre de la zona mezclada')
            ->assertSee('Mezcla manual de zonas')
            ->assertSee('se mezcla con');

        $this->actingAs($admin)
            ->post(route('workspace.categories.fixture.generate', $category), [
                'start_at' => '2026-12-26 09:00',
                'gap_minutes' => 15,
                'priority' => 'field',
                'published' => 0,
                'scope' => 'group',
                'legs' => 'ida',
                'stage' => '1º Fase',
                'fill_byes' => 1,
                'confirm_uneven' => 1,
                'mix_pairs' => ['B' => 'C'],
            ])
            ->assertRedirect();

        $this->assertSame(15, \App\Models\FixtureMatch::query()->where('category_id', $category->id)->count());

        $fecha1 = \App\Models\FixtureMatch::query()
            ->with(['homeTeam', 'awayTeam'])
            ->where('category_id', $category->id)
            ->where('round', 'Fecha 1')
            ->get();

        $this->assertSame(5, $fecha1->count());
        $this->assertTrue($fecha1->contains(function ($match) {
            $groups = collect([$match->homeTeam->group_name, $match->awayTeam->group_name])->sort()->values()->all();

            return $groups === ['B', 'C'];
        }));

        $fecha2 = \App\Models\FixtureMatch::query()
            ->with(['homeTeam', 'awayTeam'])
            ->where('category_id', $category->id)
            ->where('round', 'Fecha 2')
            ->get();

        $this->assertSame(5, $fecha2->count());
        $this->assertTrue($fecha2->contains(function ($match) {
            $groups = collect([$match->homeTeam->group_name, $match->awayTeam->group_name])->sort()->values()->all();

            return $groups === ['B', 'C'];
        }));

        $this->actingAs($admin)
            ->get(route('workspace.categories.standings', $category))
            ->assertOk()
            ->assertSee('Grupo A')
            ->assertSee('Grupo B')
            ->assertSee('Interzonal');

        $knockout = \App\Models\FixtureMatch::query()->where('category_id', $category->id)->firstOrFail();
        $knockout->update(['stage' => 'Semifinal de oro', 'round' => 'Semi Oro']);

        $this->actingAs($admin)
            ->get(route('workspace.categories.standings', [$category, 'phase' => 'all']))
            ->assertOk()
            ->assertSee('Semifinal de oro');

        $this->actingAs($admin)
            ->from(route('workspace.categories.fixture', $category))
            ->delete(route('workspace.categories.matches.destroy-all', $category), [
                'confirm' => 'BORRAR',
            ])
            ->assertRedirect();

        $this->assertSame(0, \App\Models\FixtureMatch::query()->where('category_id', $category->id)->count());
    }

    public function test_fixture_mixes_c_with_d_and_e_with_f_for_fecha_one_byes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $base = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $category = Category::create([
            'tournament_id' => $base->tournament_id,
            'name' => 'Sub 11 Mezcla Manual Operación',
            'birth_year' => '2015',
            'format' => 'Fútbol 11',
            'modality' => 'Fútbol 11',
            'branch' => 'Masculina',
            'status' => 'active',
            'groups_count' => 6,
        ]);

        foreach ([
            ['A1', 'A'], ['A2', 'A'], ['A3', 'A'], ['A4', 'A'],
            ['B1', 'B'], ['B2', 'B'], ['B3', 'B'], ['B4', 'B'],
            ['C1', 'C'], ['C2', 'C'], ['C3', 'C'],
            ['D1', 'D'], ['D2', 'D'], ['D3', 'D'],
            ['E1', 'E'], ['E2', 'E'], ['E3', 'E'],
            ['F1', 'F'], ['F2', 'F'], ['F3', 'F'],
        ] as [$name, $group]) {
            Team::create([
                'tournament_id' => $category->tournament_id,
                'category_id' => $category->id,
                'name' => $name,
                'delegation_name' => $name,
                'group_name' => $group,
                'status' => 'approved',
            ]);
        }

        $this->actingAs($admin)
            ->post(route('workspace.categories.fixture.generate', $category), [
                'start_at' => '2026-12-27 09:00',
                'gap_minutes' => 15,
                'priority' => 'field',
                'published' => 0,
                'scope' => 'group',
                'legs' => 'ida',
                'stage' => '1º Fase',
                'fill_byes' => 1,
                'confirm_uneven' => 1,
            ])
            ->assertRedirect();

        $fecha1 = \App\Models\FixtureMatch::query()
            ->with(['homeTeam', 'awayTeam'])
            ->where('category_id', $category->id)
            ->where('round', 'Fecha 1')
            ->get();

        $this->assertSame(10, $fecha1->count());

        $groupCounts = $fecha1->flatMap(function ($match) {
            return [$match->homeTeam->group_name, $match->awayTeam->group_name];
        })->countBy();

        $this->assertSame(4, $groupCounts['A']);
        $this->assertSame(4, $groupCounts['B']);
        $this->assertSame(3, $groupCounts['C']);
        $this->assertSame(3, $groupCounts['D']);
        $this->assertSame(3, $groupCounts['E']);
        $this->assertSame(3, $groupCounts['F']);

        $crosses = $fecha1->map(function ($match) {
            return collect([$match->homeTeam->group_name, $match->awayTeam->group_name])->sort()->values()->all();
        })->filter(fn ($groups) => $groups[0] !== $groups[1])->values();

        $this->assertCount(2, $crosses);
        $this->assertTrue($crosses->contains(fn ($groups) => $groups === ['C', 'D']));
        $this->assertTrue($crosses->contains(fn ($groups) => $groups === ['E', 'F']));

        $fecha2 = \App\Models\FixtureMatch::query()
            ->with(['homeTeam', 'awayTeam'])
            ->where('category_id', $category->id)
            ->where('round', 'Fecha 2')
            ->get();

        $this->assertSame(10, $fecha2->count());
        $fecha2Crosses = $fecha2->map(function ($match) {
            return collect([$match->homeTeam->group_name, $match->awayTeam->group_name])->sort()->values()->all();
        })->filter(fn ($groups) => $groups[0] !== $groups[1])->values();
        $this->assertCount(2, $fecha2Crosses);
        $this->assertTrue($fecha2Crosses->contains(fn ($groups) => $groups === ['C', 'D']));
        $this->assertTrue($fecha2Crosses->contains(fn ($groups) => $groups === ['E', 'F']));

        $this->actingAs($admin)
            ->get(route('workspace.categories.fixture', $category))
            ->assertOk()
            ->assertSee('Fecha 1')
            ->assertSee('Fecha 2')
            ->assertSee('Interzonal')
            ->assertSee('Grupo C vs Grupo D')
            ->assertSee('Grupo E vs Grupo F');
    }

    public function test_standings_sum_real_match_scores_even_if_still_scheduled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $base = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $category = Category::create([
            'tournament_id' => $base->tournament_id,
            'name' => 'Sub 11 Tabla Operación',
            'birth_year' => '2015',
            'format' => 'Fútbol 11',
            'modality' => 'Fútbol 11',
            'branch' => 'Masculina',
            'status' => 'active',
            'groups_count' => 1,
            'points_win' => 3,
            'points_draw' => 1,
            'points_loss' => 0,
        ]);
        $home = Team::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'name' => 'Almirante Tabla',
            'delegation_name' => 'Almirante Tabla',
            'group_name' => 'A',
            'status' => 'approved',
        ]);
        $away = Team::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'name' => 'Santa Teresita Tabla',
            'delegation_name' => 'Santa Teresita Tabla',
            'group_name' => 'A',
            'status' => 'approved',
        ]);
        $other = Team::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'name' => 'Selectivo Tabla',
            'delegation_name' => 'Selectivo Tabla',
            'group_name' => 'A',
            'status' => 'approved',
        ]);
        $field = Field::query()->whereHas('venue', fn ($query) => $query->where('tournament_id', $category->tournament_id))->firstOrFail();

        \App\Models\FixtureMatch::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'field_id' => $field->id,
            'scheduled_at' => '2026-08-12 10:20:00',
            'stage' => '1º Fase',
            'round' => 'Fecha 1',
            'status' => 'scheduled',
            'home_score' => 1,
            'away_score' => 0,
            'published' => false,
        ]);
        \App\Models\FixtureMatch::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'home_team_id' => $home->id,
            'away_team_id' => $other->id,
            'field_id' => $field->id,
            'scheduled_at' => '2026-08-12 09:00:00',
            'stage' => '1º Fase',
            'round' => 'Fecha 1',
            'status' => 'scheduled',
            'published' => false,
        ]);

        $groups = app(\App\Services\CompetitionBoard::class)->standingsByGroup($category);
        $rows = $groups->get('Grupo A');
        $this->assertNotNull($rows);

        $almirante = $rows->firstWhere(fn ($row) => $row['team']->id === $home->id);
        $santa = $rows->firstWhere(fn ($row) => $row['team']->id === $away->id);
        $selectivo = $rows->firstWhere(fn ($row) => $row['team']->id === $other->id);

        $this->assertSame(3, $almirante['points']);
        $this->assertSame(1, $almirante['played']);
        $this->assertSame(1, $almirante['won']);
        $this->assertSame(1, $almirante['gf']);
        $this->assertSame(0, $almirante['ga']);
        $this->assertSame(1, $almirante['gd']);
        $this->assertSame(100, $almirante['percent']);
        $this->assertSame(1, $almirante['pending']);

        $this->assertSame(0, $santa['points']);
        $this->assertSame(1, $santa['played']);
        $this->assertSame(1, $santa['lost']);
        $this->assertSame(0, $santa['gf']);
        $this->assertSame(1, $santa['ga']);

        $this->assertSame(0, $selectivo['played']);
        $this->assertSame(1, $selectivo['pending']);

        $this->actingAs($admin)
            ->get(route('workspace.categories.standings', $category))
            ->assertOk()
            ->assertSee('ALMIRANTE TABLA')
            ->assertSee('Por jugar')
            ->assertSee('2 de 2 partidos')
            ->assertSee('Fecha 1');
    }

    public function test_standings_count_interzonal_results_in_each_group(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $base = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $category = Category::create([
            'tournament_id' => $base->tournament_id,
            'name' => 'Sub 11 Interzonal Tabla',
            'birth_year' => '2015',
            'format' => 'Fútbol 11',
            'modality' => 'Fútbol 11',
            'branch' => 'Masculina',
            'status' => 'active',
            'groups_count' => 2,
            'points_win' => 3,
            'points_draw' => 1,
            'points_loss' => 0,
        ]);
        $home = Team::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'name' => 'Olimpia Interzonal',
            'delegation_name' => 'Olimpia Interzonal',
            'group_name' => 'E',
            'status' => 'approved',
        ]);
        $away = Team::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'name' => 'Malvinas Interzonal',
            'delegation_name' => 'Malvinas Interzonal',
            'group_name' => 'F',
            'status' => 'approved',
        ]);
        $field = Field::query()->whereHas('venue', fn ($query) => $query->where('tournament_id', $category->tournament_id))->firstOrFail();
        $match = \App\Models\FixtureMatch::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'field_id' => $field->id,
            'scheduled_at' => '2026-08-12 10:20:00',
            'stage' => '1º Fase',
            'round' => 'Fecha 1',
            'status' => 'finished',
            'home_score' => 2,
            'away_score' => 3,
            'published' => true,
        ]);

        $groups = app(\App\Services\CompetitionBoard::class)->standingsByGroup($category);
        $olimpia = $groups->get('Grupo E')->firstWhere(fn ($row) => $row['team']->id === $home->id);
        $malvinas = $groups->get('Grupo F')->firstWhere(fn ($row) => $row['team']->id === $away->id);

        $this->assertSame(0, $olimpia['points']);
        $this->assertSame(1, $olimpia['played']);
        $this->assertSame(2, $olimpia['gf']);
        $this->assertSame(3, $olimpia['ga']);
        $this->assertSame(3, $malvinas['points']);
        $this->assertSame(1, $malvinas['played']);
        $this->assertSame(3, $malvinas['gf']);
        $this->assertSame(2, $malvinas['ga']);

        $this->actingAs($admin)
            ->get(route('workspace.categories.standings', $category))
            ->assertOk()
            ->assertSee('OLIMPIA INTERZONAL')
            ->assertSee('MALVINAS INTERZONAL');

        $this->actingAs($admin)
            ->get(route('workspace.categories.matches.show', [$category, $match]))
            ->assertOk()
            ->assertSee('Olimpia Interzonal')
            ->assertSee('Malvinas Interzonal')
            ->assertSee('2 : 3');
    }

    public function test_saving_groups_shows_them_in_the_category(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('workspace.categories.settings.update', $category), [
                'section' => 'groups',
                'groups_count' => 3,
                'assign' => 'keep',
            ])
            ->assertRedirect(route('workspace.categories.standings', $category));

        $this->assertSame(3, $category->fresh()->groups_count);

        $this->actingAs($admin)
            ->get(route('workspace.categories.standings', $category))
            ->assertOk()
            ->assertSee('Grupos actualizados')
            ->assertSee('GRUPO A')
            ->assertSee('GRUPO B')
            ->assertSee('GRUPO C')
            ->assertSeeInOrder(['GRUPO A', 'GRUPO B', 'GRUPO C'])
            ->assertSee('Todavía no hay equipos en este grupo');

        $this->actingAs($admin)
            ->get(route('workspace.categories.home', $category))
            ->assertOk()
            ->assertSee('Equipos')
            ->assertSee('Información');

        $this->actingAs($admin)
            ->get(route('workspace.categories.settings', $category))
            ->assertOk()
            ->assertSee('Grupos (3)');

        $team = $category->teams()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.categories.teams', $category))
            ->assertOk()
            ->assertSee('name="group_name"', false)
            ->assertSee('Grupo A')
            ->assertSee('Grupo B')
            ->assertSee('Grupo C')
            ->assertDontSee('placeholder="Grupo"', false);

        $this->actingAs($admin)
            ->get(route('workspace.categories.teams.show', [$category, $team]))
            ->assertOk()
            ->assertSee('name="group_name"', false)
            ->assertSee('Grupo A')
            ->assertSee('Grupo C');

        $this->actingAs($admin)
            ->patch(route('workspace.categories.teams.update', [$category, $team]), [
                'name' => $team->name,
                'delegation_name' => $team->delegation_name,
                'group_name' => 'C',
                'status' => $team->status,
            ])
            ->assertRedirect();

        $this->assertSame('C', $team->fresh()->group_name);

        $this->actingAs($admin)
            ->post(route('workspace.categories.settings.update', $category), [
                'section' => 'groups',
                'groups_count' => 3,
                'assign' => 'keep',
                'group_names' => [
                    'A' => 'Zona Norte',
                    'B' => 'Zona Sur',
                    'C' => 'Zona Este',
                ],
            ])
            ->assertRedirect(route('workspace.categories.standings', $category));

        $this->actingAs($admin)
            ->get(route('workspace.categories.standings', $category))
            ->assertOk()
            ->assertSee('ZONA NORTE')
            ->assertSee('ZONA SUR')
            ->assertSee('ZONA ESTE')
            ->assertSee('Editar nombre del grupo');
    }

    public function test_delegate_and_assistant_referee_cannot_change_match_schedule(): void
    {
        $this->seed(DatabaseSeeder::class);

        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $club = \App\Models\Delegation::query()
            ->where('tournament_id', $category->tournament_id)
            ->where('name', 'Leones')
            ->firstOrFail();
        $category->teams()->where('name', 'Leones FC')->update(['delegation_id' => $club->id]);
        $match = \App\Models\FixtureMatch::query()->where('category_id', $category->id)->firstOrFail();
        $field = Field::query()
            ->whereHas('venue', fn ($query) => $query->where('tournament_id', $category->tournament_id))
            ->firstOrFail();
        $schedulePayload = [
            'field_id' => $field->id,
            'scheduled_at' => '2026-12-26 18:00',
        ];
        $originalAt = $match->scheduled_at?->format('Y-m-d H:i:s');
        $delegate = User::where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $assistant = User::where('email', 'coordinador@stctorneos.demo')->firstOrFail();
        $lucia = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();

        $this->actingAs($delegate)
            ->get(route('workspace.categories.matches.show', [$category, $match]))
            ->assertOk()
            ->assertSee('Resumen del partido')
            ->assertDontSee('Planilla en blanco')
            ->assertDontSee('Guardar horario')
            ->assertDontSee('Guardar resultado')
            ->assertDontSee('Cerrar planilla y publicar')
            ->assertDontSee('data-ws-player-filter', false);

        $this->actingAs($delegate)
            ->get(route('workspace.categories.fixture', $category))
            ->assertOk()
            ->assertDontSee('Guardar horario')
            ->assertDontSee('Agregar partido')
            ->assertDontSee('Generar partidos');

        $this->actingAs($delegate)
            ->patch(route('workspace.categories.matches.schedule', [$category, $match]), $schedulePayload)
            ->assertForbidden();

        $this->actingAs($delegate)
            ->patch(route('workspace.categories.matches.result', [$category, $match]), [
                'home_score' => 9,
                'away_score' => 9,
                'status' => 'live',
            ])
            ->assertForbidden();

        $this->actingAs($delegate)
            ->post(route('workspace.categories.matches.events', [$category, $match]), [
                'type' => 'goal',
                'team_id' => $match->home_team_id,
                'minute' => 10,
            ])
            ->assertForbidden();

        $this->actingAs($assistant)
            ->get(route('workspace.categories.matches.show', [$category, $match]))
            ->assertOk()
            ->assertDontSee('Guardar horario')
            ->assertSee('Guardar resultado');

        $this->actingAs($assistant)
            ->get(route('workspace.categories.fixture', $category))
            ->assertOk()
            ->assertDontSee('Agregar partido')
            ->assertDontSee('Generar partidos');

        $this->actingAs($assistant)
            ->patch(route('workspace.categories.matches.schedule', [$category, $match]), $schedulePayload)
            ->assertForbidden();

        $this->assertSame($originalAt, $match->fresh()->scheduled_at?->format('Y-m-d H:i:s'));

        $this->actingAs($lucia)
            ->get(route('workspace.categories.matches.show', [$category, $match]))
            ->assertOk()
            ->assertSee('Guardar horario')
            ->assertSee('Guardar resultado');

        $this->actingAs($lucia)
            ->patch(route('workspace.categories.matches.schedule', [$category, $match]), $schedulePayload)
            ->assertRedirect();

        $this->assertSame('2026-12-26 18:00:00', $match->fresh()->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_assistant_referee_can_view_teams_edit_players_when_open_and_save_results(): void
    {
        $this->seed(DatabaseSeeder::class);

        $assistant = User::where('email', 'coordinador@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $team = $category->teams()->firstOrFail();
        $player = Player::query()->where('team_id', $team->id)->firstOrFail();
        $match = \App\Models\FixtureMatch::query()->where('category_id', $category->id)->firstOrFail();

        \App\Services\RegistrationControl::setCategoryOpen($category, true);

        $this->actingAs($assistant)
            ->get(route('workspace.categories.home', $category))
            ->assertOk()
            ->assertDontSee('Configuración')
            ->assertDontSee('Cambiar categoría');

        $this->actingAs($assistant)
            ->get(route('workspace.categories.settings', $category))
            ->assertForbidden();

        $this->actingAs($assistant)
            ->get(route('workspace.categories.teams', $category))
            ->assertOk()
            ->assertSee($team->name);

        $this->actingAs($assistant)
            ->post(route('workspace.categories.teams.store', $category), [
                'name' => 'Equipo Fantasma Árbitro',
                'delegation_id' => $team->delegation_id,
            ])
            ->assertForbidden();

        $this->actingAs($assistant)
            ->get(route('workspace.categories.players.show', [$category, $player]))
            ->assertOk()
            ->assertDontSee('name="first_name"', false);

        $this->actingAs($assistant)
            ->get(route('workspace.categories.players.edit', [$category, $player]))
            ->assertOk()
            ->assertSee('name="first_name"', false);

        $this->actingAs($assistant)
            ->patch(route('workspace.categories.players.update', [$category, $player]), [
                'first_name' => 'NombreArbitro',
                'last_name' => $player->last_name,
                'status' => $player->status ?: 'pending',
            ])
            ->assertRedirect();

        $this->assertSame('NombreArbitro', $player->fresh()->first_name);

        \App\Services\RegistrationControl::setCategoryOpen($category->fresh(), false);

        $this->actingAs($assistant)
            ->patch(route('workspace.categories.players.update', [$category, $player->fresh()]), [
                'first_name' => 'NoDebe',
                'last_name' => $player->last_name,
                'status' => $player->status ?: 'pending',
            ])
            ->assertForbidden();

        $this->assertSame('NombreArbitro', $player->fresh()->first_name);

        $this->actingAs($assistant)
            ->get(route('workspace.categories.standings', $category))
            ->assertOk();

        $this->actingAs($assistant)
            ->patch(route('workspace.categories.matches.result', [$category, $match]), [
                'home_score' => 2,
                'away_score' => 1,
                'status' => 'finished',
            ])
            ->assertRedirect();

        $this->assertSame(2, (int) $match->fresh()->home_score);
        $this->assertSame(1, (int) $match->fresh()->away_score);
    }

    public function test_assistant_referee_sees_all_tournaments_in_operation_gate(): void
    {
        $this->seed(DatabaseSeeder::class);

        $assistant = User::where('email', 'coordinador@stctorneos.demo')->firstOrFail();
        $expected = Tournament::query()->orderBy('name')->pluck('name')->all();

        $this->actingAs($assistant)
            ->get(route('workspace.home'))
            ->assertOk()
            ->assertSee('Elegí un torneo');

        foreach ($expected as $name) {
            $this->actingAs($assistant)
                ->get(route('workspace.home'))
                ->assertSee($name);
        }

        $this->assertGreaterThan(1, count($expected));
    }

    public function test_goal_photos_show_on_match_sheet_and_scorer_tables(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $base = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $category = Category::create([
            'tournament_id' => $base->tournament_id,
            'name' => 'Sub 11 Fotos Goles',
            'birth_year' => '2015',
            'format' => 'Fútbol 11',
            'modality' => 'Fútbol 11',
            'branch' => 'Masculina',
            'status' => 'active',
            'groups_count' => 1,
            'points_win' => 3,
            'points_draw' => 1,
            'points_loss' => 0,
        ]);
        $home = Team::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'name' => 'Munro Fotos',
            'delegation_name' => 'Munro Fotos',
            'group_name' => 'A',
            'status' => 'approved',
        ]);
        $away = Team::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'name' => 'Ciclon Fotos',
            'delegation_name' => 'Ciclon Fotos',
            'group_name' => 'A',
            'status' => 'approved',
        ]);
        $player = Player::create([
            'team_id' => $home->id,
            'first_name' => 'Isaias',
            'last_name' => 'GoleadorFoto',
            'document_number' => '40111999',
            'status' => 'enabled',
            'photo_path' => 'images/stc-logo.png',
        ]);
        $field = Field::query()->whereHas('venue', fn ($query) => $query->where('tournament_id', $category->tournament_id))->firstOrFail();
        $match = \App\Models\FixtureMatch::create([
            'tournament_id' => $category->tournament_id,
            'category_id' => $category->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'field_id' => $field->id,
            'scheduled_at' => '2026-08-20 09:00:00',
            'stage' => '1º Fase',
            'round' => 'Fecha 1',
            'status' => 'live',
        ]);

        $this->actingAs($admin)
            ->post(route('workspace.categories.matches.events', [$category, $match]), [
                'type' => 'goal',
                'team_id' => $home->id,
                'player_id' => $player->id,
                'minute' => 12,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('workspace.categories.matches.show', [$category, $match]))
            ->assertOk()
            ->assertSee('data-ws-actor-photo', false)
            ->assertSee('Jugadas del partido')
            ->assertSee('GOOL!')
            ->assertSee('GoleadorFoto')
            ->assertSee($player->photoUrl(), false);

        $this->actingAs($admin)
            ->post(route('workspace.categories.matches.close', [$category, $match]))
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('workspace.categories.standings', $category))
            ->assertOk()
            ->assertSee('data-ws-rank-photo', false)
            ->assertSee('GoleadorFoto')
            ->assertSee($player->photoUrl(), false);

        $this->actingAs($admin)
            ->get(route('workspace.categories.rankings', $category))
            ->assertOk()
            ->assertSee('data-ws-rank-photo', false)
            ->assertSee('GoleadorFoto')
            ->assertSee($player->photoUrl(), false);
    }

    public function test_competition_tabs_show_standings_brackets_and_fair_play(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.categories.standings', $category))
            ->assertOk()
            ->assertSee('Tablas')
            ->assertSee('Cruces')
            ->assertSee('Fair Play')
            ->assertSee('GRUPO A');

        $this->actingAs($admin)
            ->get(route('workspace.categories.brackets', $category))
            ->assertOk()
            ->assertSee('Tablas')
            ->assertSee('Final Oro');

        $this->actingAs($admin)
            ->get(route('workspace.categories.fairplay', $category))
            ->assertOk()
            ->assertSee('Fair Play')
            ->assertSee('Disciplina por equipo')
            ->assertSee('Principio STC')
            ->assertSee('Tarjeta amarilla a jugador')
            ->assertSee('Conducta inapropiada padre / familiar')
            ->assertSee('Criterios de desempate')
            ->assertSee('Pts');
    }

    public function test_fair_play_adult_sanctions_weigh_more_than_player_cards(): void
    {
        $this->seed(DatabaseSeeder::class);

        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $board = app(\App\Services\CompetitionBoard::class);
        $rows = $board->fairPlay($category)->keyBy(fn (array $row) => $row['team']->name);

        $pampero = $rows->get('Pampero');
        $this->assertNotNull($pampero);
        $this->assertGreaterThanOrEqual(3, $pampero['points']);
        $this->assertGreaterThan(0, $pampero['family_misconduct'] + $pampero['family_expulsion']);
    }

    public function test_workspace_match_registers_family_penalty_for_fair_play(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Categoría 2012')->firstOrFail();
        $match = \App\Models\FixtureMatch::query()
            ->where('category_id', $category->id)
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->firstOrFail();
        $team = Team::query()->findOrFail($match->home_team_id);

        $this->actingAs($admin)
            ->get(route('workspace.categories.matches.show', [$category, $match]))
            ->assertOk()
            ->assertSee('Penalizaciones Fair Play')
            ->assertSee('Cargar penalización');

        $this->actingAs($admin)
            ->post(route('workspace.categories.matches.incidents', [$category, $match]), [
                'fair_play_kind' => 'family_misconduct',
                'team_id' => $team->id,
                'title' => 'Conducta inapropiada de un padre',
                'moment' => 'Segundo tiempo',
            ])
            ->assertRedirect();

        $sheet = $match->fresh()->sheet;
        $this->assertNotNull($sheet);
        $this->assertDatabaseHas('match_sheet_incidents', [
            'match_sheet_id' => $sheet->id,
            'fair_play_kind' => 'family_misconduct',
            'related_name' => $team->name,
            'status' => 'applied',
        ]);

        $this->actingAs($admin)
            ->patch(route('workspace.categories.matches.result', [$category, $match]), [
                'home_score' => 1,
                'away_score' => 0,
                'status' => 'finished',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('workspace.categories.matches.close', [$category, $match]))
            ->assertRedirect();

        $rows = app(\App\Services\CompetitionBoard::class)->fairPlay($category)->keyBy(fn (array $row) => $row['team']->id);
        $row = $rows->get($team->id);
        $this->assertNotNull($row);
        $this->assertGreaterThan(0, $row['family_misconduct'] + $row['family_expulsion']);
        $this->assertGreaterThanOrEqual(3, $row['points']);
    }

    public function test_workspace_match_registers_staff_yellow_like_player_event(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Categoría 2012')->firstOrFail();
        $match = \App\Models\FixtureMatch::query()
            ->where('category_id', $category->id)
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->firstOrFail();
        $team = Team::query()->findOrFail($match->home_team_id);

        $staff = $team->staffMembers()->create([
            'first_name' => 'Carlos',
            'last_name' => 'Foresca',
            'role' => 'director_tecnico',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('workspace.categories.matches.events', [$category, $match]), [
                'type' => 'yellow',
                'actor_kind' => 'staff',
                'team_id' => $team->id,
                'team_staff_id' => $staff->id,
                'minute' => 33,
            ])
            ->assertRedirect();

        $event = $match->fresh()->sheet?->events()->where('team_staff_id', $staff->id)->first();
        $this->assertNotNull($event);
        $this->assertSame('staff_yellow', $event->type);
        $this->assertSame('Carlos Foresca', $event->actorName());

        $this->actingAs($admin)
            ->get(route('workspace.categories.matches.show', [$category, $match]))
            ->assertOk()
            ->assertSee('TARJETA AMARILLA')
            ->assertSee('Carlos Foresca')
            ->assertSee('Director Técnico')
            ->assertSee('Cuerpo técnico');

        $this->actingAs($admin)
            ->patch(route('workspace.categories.matches.result', [$category, $match]), [
                'home_score' => 0,
                'away_score' => 0,
                'status' => 'finished',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('workspace.categories.matches.close', [$category, $match]))
            ->assertRedirect();

        $rows = app(\App\Services\CompetitionBoard::class)->fairPlay($category)->keyBy(fn (array $row) => $row['team']->id);
        $row = $rows->get($team->id);
        $this->assertNotNull($row);
        $this->assertGreaterThan(0, $row['staff_yellow'] + $row['staff_expulsion']);
    }

    public function test_match_blank_planilla_lists_both_team_rosters(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Categoría 2012')->firstOrFail();
        $match = \App\Models\FixtureMatch::query()
            ->where('category_id', $category->id)
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.categories.matches.planilla', [$category, $match]))
            ->assertOk()
            ->assertSee('Competición:')
            ->assertSee($match->homeTeam?->name)
            ->assertSee($match->awayTeam?->name)
            ->assertSee('Goles')
            ->assertSee('Sustituciones')
            ->assertSee('Descargar PDF');

        $this->actingAs($admin)
            ->get(route('workspace.categories.planillas', $category))
            ->assertOk()
            ->assertSee('Planillas en blanco')
            ->assertSee('Descargar planillas seleccionadas');

        $this->actingAs($admin)
            ->get(route('workspace.categories.planillas.download', [
                'category' => $category,
                'round' => 'Fecha 1',
                'matches' => [$match->id],
            ]))
            ->assertOk()
            ->assertSee('Descargar 1 planilla(s)');
    }

    public function test_player_account_sees_own_ficha_and_competition_views(): void
    {
        $this->seed(DatabaseSeeder::class);

        $playerUser = User::where('email', 'jugador@stctorneos.demo')->firstOrFail();
        $category = $playerUser->playerCategory();
        $this->assertNotNull($category);

        $this->actingAs($playerUser)
            ->get(route('workspace.player.home'))
            ->assertOk()
            ->assertSee('Mi ficha')
            ->assertSee('Lautaro Ruiz')
            ->assertSee('Leones FC')
            ->assertSee('Mis documentos');

        $this->actingAs($playerUser)
            ->get(route('workspace.player.ficha'))
            ->assertOk()
            ->assertSee('Datos personales')
            ->assertSee('Delantero');

        $this->actingAs($playerUser)
            ->get(route('workspace.player.credential'))
            ->assertOk()
            ->assertSee('Lautaro Ruiz');

        $this->actingAs($playerUser)
            ->get(route('workspace.home'))
            ->assertOk()
            ->assertSee('Elegí un torneo');

        $this->actingAs($playerUser)
            ->get(route('workspace.categories.standings', $category))
            ->assertOk()
            ->assertSee('Clasificación');

        $this->actingAs($playerUser)
            ->get(route('workspace.categories.fixture', $category))
            ->assertOk()
            ->assertDontSee('Planillas en blanco')
            ->assertDontSee('Planilla en blanco');

        $match = \App\Models\FixtureMatch::query()->where('category_id', $category->id)->first();
        if ($match) {
            $this->actingAs($playerUser)
                ->get(route('workspace.categories.matches.show', [$category, $match]))
                ->assertOk()
                ->assertSee('Resumen del partido')
                ->assertDontSee('Planilla en blanco')
                ->assertDontSee('Cargar jugada');

            $this->actingAs($playerUser)
                ->get(route('workspace.categories.matches.planilla', [$category, $match]))
                ->assertForbidden();

            $this->actingAs($playerUser)
                ->get(route('workspace.categories.planillas', $category))
                ->assertForbidden();
        }

        $this->actingAs($playerUser)
            ->get(route('workspace.categories.standings', $category))
            ->assertOk()
            ->assertDontSee('Planillas en blanco');

        $this->actingAs($playerUser)
            ->get(route('workspace.categories.rankings', $category))
            ->assertOk()
            ->assertSee('Rankings');
    }
}
