<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\ContentPost;
use App\Models\Delegation;
use App\Models\Field;
use App\Models\FixtureMatch;
use App\Models\MatchSheet;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\Role;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_demo_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_home_redirects_authenticated_user_to_workspace(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'admin@stctorneos.demo')->firstOrFail();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('workspace.home'));

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect(route('workspace.home'));
    }

    public function test_demo_login_renders(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Iniciar sesión');
        $response->assertSee('lucia@stctorneos.demo');
        $response->assertSee('Admin Torneo');
        $response->assertSee('Asistente/árbitro');
        $response->assertSee('Delegado');
        $response->assertSee('Tutor');
        $response->assertSee('tutor@stctorneos.demo');
        $response->assertSee('stctutor');
        $response->assertDontSee('Coordinador · Diego Ruiz');
        $response->assertDontSee('Árbitro · Martín Sosa');
        $response->assertSee('stcdemo');
    }

    public function test_demo_login_authenticates(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->post('/login', [
            'email' => 'admin@stctorneos.demo',
            'password' => 'stcdemo',
        ]);

        $response->assertRedirect(route('workspace.home'));
        $this->assertAuthenticated();
    }

    public function test_demo_tutor_login_authenticates(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->post('/login', [
            'email' => 'tutor@stctorneos.demo',
            'password' => 'stctutor',
        ]);

        $response->assertRedirect(route('workspace.tutor.home'));
        $this->assertAuthenticated();
    }

    public function test_admin_dashboard_renders(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'admin@stctorneos.demo')->firstOrFail();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Dashboard Admin');
        $response->assertSee('Santa Teresita Cup 2026');
        $response->assertSee('Próxima jornada');
        $response->assertSee('Pampero');
        $response->assertSee('Categorías activas');
        $response->assertSee('Delegaciones');
        $response->assertSee('Jugadores registrados');
        $response->assertSee('Jugadores habilitados');
        $response->assertSee('Fichas pendientes');
        $response->assertSee('Documentación pendiente');
        $response->assertSee('Partidos en vivo');
        $response->assertSee('Partidos finalizados');
        $response->assertSee('Alertas y tareas pendientes');
        $response->assertSee('Acciones');
        $response->assertSee('Todos los torneos');
        $response->assertSee('Admin General · todos los torneos');
        $response->assertSee('Ir al contexto operativo');
        $response->assertSee('Partido en vivo');
        $response->assertSee('Jornada');
        $response->assertSee('Fixture');
        $response->assertSee('Resultados');
        $response->assertDontSee('Datos MySQL');
        $response->assertSee('/admin/categorias?', false);
        $response->assertSee('/admin/delegaciones', false);
        $response->assertSee('/admin/jugadores?status=enabled', false);
        $response->assertSee('/admin/fixture', false);
        $response->assertSee('/admin/resultados', false);
    }

    public function test_admin_module_renders(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'admin@stctorneos.demo')->firstOrFail();

        $response = $this->actingAs($user)->get('/admin/torneos');

        $response->assertOk();
        $response->assertSee('Torneos');
        $response->assertSee('Santa Teresita Cup 2026');
        $response->assertSee('Duplicar');
        $response->assertSee('Eliminar');

        $this->actingAs($user)
            ->get(route('admin.tournaments.index', ['search' => 'Buenos Aires']))
            ->assertOk()
            ->assertSee('STC Buenos Aires 2026')
            ->assertDontSee('Liga Kids Norte');

        $this->actingAs($user)
            ->post(route('admin.tournaments.store'), [
                'name' => 'Copa Demo Eliminar',
                'edition' => 'Prueba',
                'country' => 'Argentina',
                'city' => 'La Plata',
                'venue_name' => 'Predio Test',
                'timezone' => 'America/Argentina/Buenos_Aires',
                'starts_at' => '2026-10-01',
                'ends_at' => '2026-10-03',
                'status' => 'draft',
                'visibility' => 'private',
                'contact_name' => 'Mesa',
                'contact_email' => 'mesa@stc.demo',
                'description' => 'Torneo de prueba',
            ])
            ->assertRedirect();

        $created = Tournament::where('name', 'Copa Demo Eliminar')->firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.tournaments.duplicate', $created))
            ->assertRedirect();

        $this->assertDatabaseHas('tournaments', ['name' => 'Copia de Copa Demo Eliminar', 'status' => 'draft']);

        $this->actingAs($user)
            ->delete(route('admin.tournaments.destroy', $created))
            ->assertRedirect(route('admin.tournaments.index'));

        $this->assertDatabaseMissing('tournaments', ['id' => $created->id]);

        $tournamentAdmin = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $this->actingAs($tournamentAdmin)
            ->get(route('admin.tournaments.create'))
            ->assertForbidden();
    }

    public function test_users_and_roles_module_renders_from_database(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'admin@stctorneos.demo')->firstOrFail();

        $this->actingAs($user)
            ->get('/admin/usuarios')
            ->assertOk()
            ->assertSee('Usuarios y Roles')
            ->assertSee('Matriz de usuarios')
            ->assertSee('Super Admin')
            ->assertSee('Sebastián Martínez')
            ->assertSee('Carla Pérez')
            ->assertSee('Pendiente');

        $delegate = User::where('email', 'delegado@stctorneos.demo')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.users.show', $delegate))
            ->assertOk()
            ->assertSee('Detalle Usuario y Permisos')
            ->assertSee('Ver jugadores')
            ->assertSee('Operar partido');

        $this->actingAs($user)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Crear / Editar Usuario');

        $role = Role::where('slug', 'asistente-arbitro')->firstOrFail();
        $tournament = Tournament::where('slug', 'santa-teresita-cup-2026')->firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.users.store'), [
                'name' => 'Nuevo Asistente Demo',
                'email' => 'coordinador.nuevo@stctorneos.demo',
                'phone' => '+54 11 5555 0199',
                'role_id' => $role->id,
                'tournament_id' => $tournament->id,
                'status' => 'pending',
            ])
            ->assertRedirect();

        $created = User::where('email', 'coordinador.nuevo@stctorneos.demo')->firstOrFail();
        $this->assertDatabaseHas('users', [
            'id' => $created->id,
            'status' => 'pending',
        ]);
        $this->assertTrue($created->hasRole('asistente-arbitro'));
        $this->assertDatabaseHas('invitations', [
            'email' => 'coordinador.nuevo@stctorneos.demo',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('admin.users.edit', $created))
            ->assertOk()
            ->assertSee('Guardar usuario');

        $this->actingAs($user)
            ->put(route('admin.users.update', $created), [
                'name' => 'Nuevo Asistente Demo',
                'email' => 'coordinador.nuevo@stctorneos.demo',
                'phone' => '+54 11 5555 0199',
                'role_id' => $role->id,
                'tournament_id' => $tournament->id,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.users.show', $created));

        $this->actingAs($user)
            ->patch(route('admin.users.status', $created), ['status' => 'suspended', 'suspension_reason' => 'Prueba operativa'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $created->id,
            'status' => 'suspended',
        ]);

        $this->actingAs($user)
            ->delete(route('admin.users.destroy', $created))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $created->id]);
    }

    public function test_categories_crud_controls_work(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Categoría 2005/2006')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.categories.index', ['search' => '2005', 'status' => 'active', 'modality' => 'Fútbol 11']))
            ->assertOk()
            ->assertSee('Categoría 2005/2006')
            ->assertSee('2005/2006')
            ->assertSee('Masculina')
            ->assertSee('Duplicar')
            ->assertSee('Cambiar')
            ->assertSee('Eliminar');

        $this->actingAs($user)
            ->post(route('admin.categories.duplicate', $category))
            ->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'name' => 'Copia de Categoría 2005/2006',
            'status' => 'draft',
            'tournament_id' => $category->tournament_id,
        ]);

        $copy = Category::where('name', 'Copia de Categoría 2005/2006')->firstOrFail();
        $this->assertSame(0, $copy->teams()->count());

        $floripa = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $foreign = Category::create([
            'tournament_id' => $floripa->id,
            'name' => 'Sub 13 Floripa',
            'birth_year' => '2013',
            'format' => 'Fútbol 11',
            'modality' => 'Fútbol 11',
            'branch' => 'Masculina',
            'status' => 'active',
        ]);

        $tournamentAdmin = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();

        $this->actingAs($tournamentAdmin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Categoría 2005/2006')
            ->assertDontSee('Sub 13 Floripa');

        $this->actingAs($tournamentAdmin)
            ->get(route('admin.categories.show', $foreign))
            ->assertForbidden();

        $this->actingAs($user)
            ->patch(route('admin.categories.status', $category), ['status' => 'draft'])
            ->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'status' => 'draft',
        ]);

        $matchId = FixtureMatch::where('category_id', $category->id)->value('id');

        $this->actingAs($user)
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);

        if ($matchId !== null) {
            $this->assertDatabaseMissing('matches', ['id' => $matchId]);
        }
    }

    public function test_category_setup_and_competition_views_render(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Categoría 2005/2006')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.categories.create'))
            ->assertOk()
            ->assertSee('1. Datos')
            ->assertSee('2. Competencia')
            ->assertSee('3. Reglas')
            ->assertSee('Modalidad personalizada')
            ->assertSee('Equipos por grupo')
            ->assertSee('Fases / llaves')
            ->assertSee('Criterios de clasificación');

        $this->actingAs($user)
            ->get(route('admin.categories.show', $category))
            ->assertOk()
            ->assertSee('Plantel y partido')
            ->assertSee('Fases y llaves')
            ->assertSee($category->statusLabel());

        $this->actingAs($user)
            ->get(route('admin.categories.competition', $category))
            ->assertOk()
            ->assertSee('Fase')
            ->assertSee('Fecha')
            ->assertSee('Estadísticas de la fecha');
    }

    public function test_delegations_and_teams_modules_render_from_database(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'admin@stctorneos.demo')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.delegations.index'))
            ->assertOk()
            ->assertSee('Listado de delegaciones')
            ->assertSee('San Lorenzo')
            ->assertSee('Martín Sosa')
            ->assertSee('Eliminar')
            ->assertSee('Equipos');

        $this->actingAs($user)
            ->get(route('admin.delegations.show', Delegation::where('name', 'San Lorenzo')->firstOrFail()))
            ->assertOk()
            ->assertSee('Avance de inscripción')
            ->assertSee('Jugadores registrados')
            ->assertSee('Documentación pendiente')
            ->assertSee('Asignar delegado');

        $floripa = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        Delegation::create([
            'tournament_id' => $floripa->id,
            'name' => 'Floripa United',
            'country' => 'Brasil',
            'city' => 'Florianópolis',
            'delegate_name' => 'Joao Silva',
            'status' => 'pending',
        ]);

        $tournamentAdmin = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $this->actingAs($tournamentAdmin)
            ->get(route('admin.delegations.index'))
            ->assertOk()
            ->assertSee('San Lorenzo')
            ->assertDontSee('Floripa United');

        $this->actingAs($user)
            ->get(route('admin.teams.index'))
            ->assertOk()
            ->assertSee('Equipos inscritos')
            ->assertSee('San Lorenzo')
            ->assertSee('Eliminar')
            ->assertSee('Habilitados');

        $team = Team::where('name', 'San Lorenzo')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.teams.show', $team))
            ->assertOk()
            ->assertSee('Cuerpo técnico')
            ->assertSee('Lista de Buena Fe')
            ->assertSee('Estadísticas')
            ->assertSee('Eliminar equipo')
            ->assertSee('Director Técnico');

        $this->actingAs($user)
            ->post(route('admin.teams.staff.store', $team), [
                'first_name' => 'Pablo',
                'last_name' => 'Rivas',
                'document_number' => '30111000',
                'role' => 'ayudante_campo',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.teams.show', $team));

        $this->assertDatabaseHas('team_staff', [
            'team_id' => $team->id,
            'role' => 'ayudante_campo',
            'last_name' => 'Rivas',
        ]);

        $ayudante = \App\Models\TeamStaff::where('team_id', $team->id)->where('role', 'ayudante_campo')->firstOrFail();
        $this->actingAs($user)
            ->get(route('admin.teams.staff.credential', [$team, $ayudante]))
            ->assertOk()
            ->assertSee('Pablo Rivas')
            ->assertSee('Ayudante de Campo');

        $floripa = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $foreignTeam = Team::create([
            'tournament_id' => $floripa->id,
            'category_id' => Category::firstOrFail()->id,
            'name' => 'Floripa Kids',
            'delegation_name' => 'Floripa United',
            'status' => 'pending',
            'player_capacity' => 14,
        ]);

        $tournamentAdmin = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $this->actingAs($tournamentAdmin)
            ->get(route('admin.teams.index'))
            ->assertOk()
            ->assertSee('San Lorenzo')
            ->assertDontSee('Floripa Kids');

        $this->actingAs($tournamentAdmin)
            ->get(route('admin.teams.show', $foreignTeam))
            ->assertForbidden();
    }

    public function test_delegation_and_team_status_updates_work(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $delegation = Delegation::firstOrFail();
        $team = Team::firstOrFail();

        $this->actingAs($user)
            ->patch(route('admin.delegations.status', $delegation), ['status' => 'observed'])
            ->assertRedirect();

        $this->assertDatabaseHas('delegations', ['id' => $delegation->id, 'status' => 'observed']);

        $this->actingAs($user)
            ->patch(route('admin.teams.status', $team), ['status' => 'blocked'])
            ->assertRedirect();

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'status' => 'blocked']);
    }

    public function test_delegation_crud_create_update_delete_works(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $tournament = Tournament::firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.delegations.store'), [
                'tournament_id' => $tournament->id,
                'name' => 'Club Demo Norte',
                'country' => 'Argentina',
                'city' => 'Tigre',
                'logo_path' => 'images/stc-logo.png',
                'delegate_name' => 'Juan Demo',
                'delegate_email' => 'juan.demo@club.test',
                'delegate_phone' => '+54 11 5555-0000',
                'status' => 'pending',
                'notes' => 'Alta demo.',
            ])
            ->assertRedirect();

        $delegation = Delegation::where('name', 'Club Demo Norte')->firstOrFail();

        $this->actingAs($user)
            ->put(route('admin.delegations.update', $delegation), [
                'tournament_id' => $tournament->id,
                'name' => 'Club Demo Norte Actualizado',
                'country' => 'Argentina',
                'city' => 'Tigre',
                'logo_path' => 'images/stc-logo.png',
                'delegate_name' => 'Juan Demo',
                'delegate_email' => 'juan.demo@club.test',
                'delegate_phone' => '+54 11 5555-0000',
                'status' => 'approved',
                'notes' => 'Actualizado.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('delegations', [
            'id' => $delegation->id,
            'name' => 'Club Demo Norte Actualizado',
            'status' => 'approved',
        ]);

        $this->actingAs($user)
            ->delete(route('admin.delegations.destroy', $delegation))
            ->assertRedirect(route('admin.delegations.index'));

        $this->assertDatabaseMissing('delegations', ['id' => $delegation->id]);
    }

    public function test_players_module_renders_from_database(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'admin@stctorneos.demo')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.players.index'))
            ->assertOk()
            ->assertSee('Lista de Buena Fe')
            ->assertSee('Thiago Martínez')
            ->assertSee('Validar cumplimiento')
            ->assertSee('Todos los equipos')
            ->assertSee('Todas las delegaciones')
            ->assertSee('WhatsApp')
            ->assertSee('wa.me/541155551234', false)
            ->assertDontSee('Foto no autorizada');

        $player = Player::where('first_name', 'Thiago')->firstOrFail();
        $team = Team::where('name', 'San Lorenzo')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.players.index', ['team_id' => $team->id]))
            ->assertOk()
            ->assertSee('Capa 1')
            ->assertSee('Capa 2')
            ->assertSee('Cerrar lista')
            ->assertSee('Thiago Martínez');

        $this->actingAs($user)
            ->patch(route('admin.players.roster'), [
                'team_id' => $team->id,
                'roster_open' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'roster_open' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('admin.players.index', ['team_id' => $team->id]))
            ->assertOk()
            ->assertSee('Reabrir lista');

        $this->actingAs($user)
            ->get(route('admin.players.show', $player))
            ->assertOk()
            ->assertSee('Capa 1')
            ->assertSee('Autorizaciones');

        $this->actingAs($user)
            ->get(route('admin.players.edit', $player))
            ->assertOk()
            ->assertSee('Revisar / Editar Ficha')
            ->assertSee('Información personal')
            ->assertSee('Habilitar para jugar')
            ->assertSee('Sudamérica')
            ->assertSee('Venezuela')
            ->assertSee('WhatsApp')
            ->assertSee('Ver foto');

        $floripa = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $foreignTeam = Team::create([
            'tournament_id' => $floripa->id,
            'category_id' => Category::firstOrFail()->id,
            'name' => 'Floripa Kids',
            'delegation_name' => 'Floripa United',
            'status' => 'pending',
            'player_capacity' => 14,
        ]);
        $foreignPlayer = Player::create([
            'team_id' => $foreignTeam->id,
            'first_name' => 'Caio',
            'last_name' => 'Floripa',
            'nationality' => 'Brasil',
            'status' => 'pending',
        ]);

        $tournamentAdmin = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $this->actingAs($tournamentAdmin)
            ->get(route('admin.players.index'))
            ->assertOk()
            ->assertSee('Thiago Martínez')
            ->assertDontSee('Caio Floripa');

        $this->actingAs($tournamentAdmin)
            ->get(route('admin.players.show', $foreignPlayer))
            ->assertForbidden();
    }

    public function test_players_crud_create_update_delete_works(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $team = Team::where('name', 'San Lorenzo')->firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.players.store'), [
                'team_id' => $team->id,
                'first_name' => 'Julián',
                'last_name' => 'Demo',
                'document_number' => '48999999',
                'birth_date' => '2014-04-04',
                'nationality' => 'Argentina',
                'position' => 'Delantero',
                'jersey_number' => 10,
                'status' => 'pending',
                'guardian_name' => 'Ana Demo',
                'guardian_relationship' => 'Madre',
                'guardian_email' => 'ana.demo@club.test',
                'guardian_phone' => '+54 11 5555-1111',
            ])
            ->assertRedirect();

        $player = Player::where('document_number', '48999999')->firstOrFail();

        $this->actingAs($user)
            ->put(route('admin.players.update', $player), [
                'team_id' => $team->id,
                'first_name' => 'Julián',
                'last_name' => 'Demo Actualizado',
                'document_number' => '48999999',
                'birth_date' => '2014-04-04',
                'nationality' => 'Argentina',
                'position' => 'Delantero',
                'jersey_number' => 10,
                'status' => 'approved',
                'guardian_name' => 'Ana Demo',
                'guardian_relationship' => 'Madre',
                'guardian_email' => 'ana.demo@club.test',
                'guardian_phone' => '+54 11 5555-1111',
                'documents' => ['DNI frente', 'Apto médico', 'Autorización'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'last_name' => 'Demo Actualizado',
            'status' => 'approved',
        ]);

        $this->actingAs($user)
            ->get(route('admin.review.index'))
            ->assertOk()
            ->assertSee('Revisión')
            ->assertSee('Ver equipos');

        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $team = Team::where('name', 'San Lorenzo')->where('category_id', $category->id)->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.review.category', $category))
            ->assertOk()
            ->assertSee('Ver jugadores');

        $this->actingAs($user)
            ->get(route('admin.review.team', [$category, $team]))
            ->assertOk()
            ->assertSee('Tutor / Padre')
            ->assertSee('Revisar')
            ->assertSee('Thiago Martínez');

        $this->actingAs($user)
            ->patch(route('admin.players.review', $player), [
                'status' => 'enabled',
                'consent_status' => 'approved',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'status' => 'enabled',
        ]);
        $this->assertDatabaseHas('guardians', [
            'player_id' => $player->id,
            'consent_status' => 'approved',
        ]);

        $this->actingAs($user)
            ->patch(route('admin.players.status', $player), ['status' => 'enabled'])
            ->assertRedirect();

        $this->assertDatabaseHas('players', ['id' => $player->id, 'status' => 'enabled']);

        $this->actingAs($user)
            ->delete(route('admin.players.destroy', $player))
            ->assertRedirect(route('admin.players.index'));

        $this->assertDatabaseMissing('players', ['id' => $player->id]);
    }

    public function test_documents_module_renders_and_review_works(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'admin@stctorneos.demo')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.documents.index'))
            ->assertOk()
            ->assertSee('Bandeja documental')
            ->assertSee('Documentación')
            ->assertSee('Mateo Díaz')
            ->assertSee('Apto médico')
            ->assertSee('Revisar')
            ->assertSee('Habilitación');

        $document = PlayerDocument::query()
            ->where('type', 'Apto médico')
            ->whereHas('player', fn ($query) => $query->where('first_name', 'Mateo')->where('last_name', 'Díaz'))
            ->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.documents.show', $document))
            ->assertOk()
            ->assertSee('Revisión documental')
            ->assertSee('Volver a bandeja documental')
            ->assertSee('Checklist validación')
            ->assertSee('Guardar revisión')
            ->assertSee('Identidad coincide')
            ->assertSee('Habilitar para jugar')
            ->assertSee('Historial de revisión');

        $this->actingAs($user)
            ->put(route('admin.documents.update', $document), [
                'status' => 'observed',
                'notes' => 'El apto médico está vencido. Solicitar renovación al padre/tutor.',
                'checklist' => [
                    'identity_match' => 'ok',
                    'tutor_signed' => 'ok',
                    'expiration_valid' => 'warn',
                    'file_readable' => 'ok',
                    'fit_to_play' => 'fail',
                ],
            ])
            ->assertRedirect(route('admin.documents.show', $document));

        $this->assertDatabaseHas('player_documents', [
            'id' => $document->id,
            'status' => 'observed',
            'notes' => 'El apto médico está vencido. Solicitar renovación al padre/tutor.',
        ]);

        $this->actingAs($user)
            ->patch(route('admin.documents.status', $document), ['status' => 'approved'])
            ->assertRedirect();

        $this->assertDatabaseHas('player_documents', [
            'id' => $document->id,
            'status' => 'approved',
        ]);

        $this->actingAs($user)
            ->get(route('admin.documents.export'))
            ->assertOk();

        $mateo = Player::where('first_name', 'Mateo')->where('last_name', 'Díaz')->firstOrFail();
        $this->actingAs($user)
            ->patch(route('admin.documents.enable', $document))
            ->assertRedirect();
        $this->assertDatabaseHas('players', ['id' => $mateo->id, 'status' => 'pending']);

        $tomas = Player::where('first_name', 'Tomás')->where('last_name', 'Fernández')->firstOrFail();
        $tomasDocument = PlayerDocument::query()->where('player_id', $tomas->id)->firstOrFail();
        $this->actingAs($user)
            ->patch(route('admin.documents.enable', $tomasDocument))
            ->assertRedirect();
        $this->assertDatabaseHas('players', ['id' => $tomas->id, 'status' => 'enabled']);

        $floripa = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $foreignTeam = Team::create([
            'tournament_id' => $floripa->id,
            'category_id' => Category::firstOrFail()->id,
            'name' => 'Floripa Kids',
            'delegation_name' => 'Floripa United',
            'status' => 'pending',
            'player_capacity' => 14,
        ]);
        $foreignPlayer = Player::create([
            'team_id' => $foreignTeam->id,
            'first_name' => 'Caio',
            'last_name' => 'Floripa',
            'nationality' => 'Brasil',
            'status' => 'pending',
        ]);
        $foreignDocument = PlayerDocument::create([
            'player_id' => $foreignPlayer->id,
            'type' => 'DNI',
            'status' => 'pending',
        ]);

        $tournamentAdmin = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $this->actingAs($tournamentAdmin)
            ->get(route('admin.documents.index'))
            ->assertOk()
            ->assertSee('Mateo Díaz')
            ->assertDontSee('Caio Floripa');

        $this->actingAs($tournamentAdmin)
            ->get(route('admin.documents.show', $foreignDocument))
            ->assertForbidden();
    }

    public function test_fixture_module_schedules_and_generates_matches(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'admin@stctorneos.demo')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.fixture.index'))
            ->assertOk()
            ->assertSee('Agenda de partidos')
            ->assertSee('Pampero vs San Lorenzo')
            ->assertSee('Generar fixture')
            ->assertSee('Conflicto');

        $match = FixtureMatch::query()
            ->whereHas('homeTeam', fn ($query) => $query->where('name', 'Pampero'))
            ->whereHas('awayTeam', fn ($query) => $query->where('name', 'San Lorenzo'))
            ->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.fixture.show', $match))
            ->assertOk()
            ->assertSee('Detalle Partido Admin')
            ->assertSee('Pampero')
            ->assertSee('Cancha 3')
            ->assertSee('Reprogramar')
            ->assertSee('Observar resultado');

        $this->actingAs($user)
            ->patch(route('admin.fixture.status', $match), [
                'status' => 'finished',
                'home_score' => 1,
                'away_score' => 2,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('matches', [
            'id' => $match->id,
            'status' => 'finished',
            'home_score' => 1,
            'away_score' => 2,
        ]);

        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $home = Team::where('name', 'Pampero')->where('category_id', $category->id)->firstOrFail();
        $away = Team::where('name', 'San Lorenzo')->where('category_id', $category->id)->firstOrFail();
        $field = Field::where('name', 'Cancha 1')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.fixture.create'))
            ->assertOk()
            ->assertSee('Día y hora')
            ->assertSee('type="date"', false)
            ->assertSee('type="time"', false)
            ->assertDontSee('datetime-local');

        $this->actingAs($user)
            ->post(route('admin.fixture.store'), [
                'tournament_id' => $category->tournament_id,
                'category_id' => $category->id,
                'field_id' => $field->id,
                'home_team_id' => $home->id,
                'away_team_id' => $away->id,
                'scheduled_at_date' => '2026-12-18',
                'scheduled_at_time' => '11:00',
                'stage' => 'Grupo A',
                'round' => 'Fecha 3',
                'status' => 'scheduled',
                'duration_minutes' => 70,
                'published' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('matches', [
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'round' => 'Fecha 3',
            'status' => 'scheduled',
        ]);

        $this->actingAs($user)
            ->get(route('admin.fixture.generate'))
            ->assertOk()
            ->assertSee('Generador / Ajuste de Fixture')
            ->assertSee('Conflictos detectados')
            ->assertSee('type="date"', false)
            ->assertDontSee('datetime-local');

        $before = FixtureMatch::query()->where('category_id', $category->id)->count();

        $this->actingAs($user)
            ->post(route('admin.fixture.generate.store'), [
                'tournament_id' => $category->tournament_id,
                'category_id' => $category->id,
                'start_at' => '2026-12-19 09:00',
                'gap_minutes' => 15,
                'priority' => 'delegation',
                'published' => 0,
            ])
            ->assertRedirect();

        $this->assertGreaterThanOrEqual($before, FixtureMatch::query()->where('category_id', $category->id)->count());

        $draft = FixtureMatch::query()
            ->whereHas('homeTeam', fn ($query) => $query->where('name', 'Zelaya'))
            ->whereHas('awayTeam', fn ($query) => $query->where('name', 'Unión FC'))
            ->firstOrFail();

        $this->actingAs($user)
            ->patch(route('admin.fixture.publish.match', $draft))
            ->assertRedirect();

        $this->assertDatabaseHas('matches', [
            'id' => $draft->id,
            'published' => 1,
        ]);

        $floripa = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $floripaCategory = Category::create([
            'tournament_id' => $floripa->id,
            'name' => 'Sub 15 Fixture Floripa',
            'birth_year' => '2011',
            'format' => 'Fútbol 11',
            'modality' => 'Fútbol 11',
            'branch' => 'Masculina',
            'status' => 'active',
        ]);
        $floripaHome = Team::create([
            'tournament_id' => $floripa->id,
            'category_id' => $floripaCategory->id,
            'name' => 'Avaí Costa',
            'delegation_name' => 'Santa Catarina',
            'city' => 'Florianópolis',
            'status' => 'approved',
        ]);
        $floripaAway = Team::create([
            'tournament_id' => $floripa->id,
            'category_id' => $floripaCategory->id,
            'name' => 'Figueirense Costa',
            'delegation_name' => 'Santa Catarina',
            'city' => 'Florianópolis',
            'status' => 'approved',
        ]);
        $floripaVenue = Venue::create([
            'tournament_id' => $floripa->id,
            'name' => 'Complejo Fixture Floripa',
            'city' => 'Florianópolis',
            'status' => 'active',
        ]);
        $floripaField = Field::create([
            'venue_id' => $floripaVenue->id,
            'name' => 'Cancha Fixture Floripa',
            'surface' => 'Césped sintético',
            'status' => 'available',
        ]);
        $foreignMatch = FixtureMatch::create([
            'tournament_id' => $floripa->id,
            'category_id' => $floripaCategory->id,
            'field_id' => $floripaField->id,
            'home_team_id' => $floripaHome->id,
            'away_team_id' => $floripaAway->id,
            'scheduled_at' => '2027-01-10 10:00:00',
            'stage' => 'Grupo A',
            'round' => 'Fecha 1',
            'status' => 'scheduled',
            'duration_minutes' => 70,
            'published' => false,
        ]);

        $tournamentAdmin = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();

        $this->actingAs($tournamentAdmin)
            ->get(route('admin.fixture.index'))
            ->assertOk()
            ->assertSee('Pampero vs San Lorenzo')
            ->assertSee('Generar fixture')
            ->assertDontSee('Avaí Costa')
            ->assertDontSee('Cancha Fixture Floripa');

        $this->actingAs($tournamentAdmin)
            ->get(route('admin.fixture.show', $foreignMatch))
            ->assertForbidden();

        $this->actingAs($tournamentAdmin)
            ->get(route('admin.fixture.generate'))
            ->assertOk()
            ->assertSee('Generador / Ajuste de Fixture')
            ->assertDontSee('STC Buenos Aires 2026')
            ->assertDontSee('Sub 15 Fixture Floripa');

        $this->actingAs($tournamentAdmin)
            ->post(route('admin.fixture.generate.store'), [
                'tournament_id' => $floripa->id,
                'category_id' => $floripaCategory->id,
                'start_at' => '2027-01-12 09:00',
                'gap_minutes' => 15,
                'priority' => 'delegation',
                'published' => 0,
            ])
            ->assertForbidden();

        $this->actingAs($tournamentAdmin)
            ->patch(route('admin.fixture.publish.match', $foreignMatch))
            ->assertForbidden();
    }

    public function test_fields_and_venues_module_works(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'admin@stctorneos.demo')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.fields.index'))
            ->assertOk()
            ->assertSee('Campos y Sedes')
            ->assertSee('Mapa de canchas')
            ->assertSee('Disponibilidad de sedes')
            ->assertSee('Santa Teresita')
            ->assertSee('Cancha 1')
            ->assertSee('Complejo Norte')
            ->assertSee('Mantenimiento');

        $norte = Field::query()->where('name', 'Cancha 1')->whereHas('venue', fn ($query) => $query->where('name', 'Complejo Norte'))->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.fields.show', $norte))
            ->assertOk()
            ->assertSee('Detalle Sede / Cancha')
            ->assertSee('Editar cancha')
            ->assertSee('Bloquear temporalmente');

        $this->actingAs($user)
            ->post(route('admin.fields.store'), [
                'tournament_id' => Tournament::where('slug', 'santa-teresita-cup-2026')->value('id'),
                'venue_name' => 'Sede Demo Costa',
                'city' => 'Santa Teresita',
                'address' => 'Calle 8 y Costanera',
                'venue_status' => 'active',
                'name' => 'Cancha 8',
                'surface' => 'Sintético',
                'modality' => 'Fútbol 7',
                'opens_at' => '08:00',
                'closes_at' => '20:00',
                'status' => 'available',
                'lighting' => 1,
            ])
            ->assertRedirect();

        $field = Field::where('name', 'Cancha 8')->firstOrFail();

        $this->assertDatabaseHas('venues', ['name' => 'Sede Demo Costa']);
        $this->assertDatabaseHas('fields', [
            'id' => $field->id,
            'name' => 'Cancha 8',
            'status' => 'available',
        ]);

        $this->actingAs($user)
            ->get(route('admin.fields.edit', $field))
            ->assertOk()
            ->assertSee('Guardar sede');

        $this->actingAs($user)
            ->patch(route('admin.fields.status', $field), ['status' => 'maintenance'])
            ->assertRedirect();

        $this->assertDatabaseHas('fields', ['id' => $field->id, 'status' => 'maintenance']);

        $this->actingAs($user)
            ->delete(route('admin.fields.destroy', $field))
            ->assertRedirect(route('admin.fields.index'));

        $this->assertDatabaseMissing('fields', ['id' => $field->id]);

        $floripa = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $foreignVenue = Venue::create([
            'tournament_id' => $floripa->id,
            'name' => 'Complejo Floripa',
            'city' => 'Florianópolis',
            'status' => 'active',
        ]);
        $foreignField = Field::create([
            'venue_id' => $foreignVenue->id,
            'name' => 'Cancha Floripa 1',
            'surface' => 'Césped sintético',
            'status' => 'available',
        ]);

        $tournamentAdmin = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $this->actingAs($tournamentAdmin)
            ->get(route('admin.fields.index'))
            ->assertOk()
            ->assertSee('Complejo Norte')
            ->assertDontSee('Cancha Floripa 1');

        $this->actingAs($tournamentAdmin)
            ->get(route('admin.fields.show', $foreignField))
            ->assertForbidden();
    }

    public function test_match_sheets_module_works(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'admin@stctorneos.demo')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.sheets.index'))
            ->assertOk()
            ->assertSee('Planillas')
            ->assertSee('San Lorenzo vs Pampero')
            ->assertSee('Cerrada')
            ->assertSee('En carga')
            ->assertSee('Borrador');

        $sheet = MatchSheet::query()->where('status', 'draft')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.sheets.datos', $sheet))
            ->assertOk()
            ->assertSee('Datos partido');

        $this->actingAs($user)
            ->put(route('admin.sheets.datos.update', $sheet), [
                'referee_name' => 'Martín Sosa',
                'assistant_name' => 'Carla Pérez',
                'continue' => 1,
            ])
            ->assertRedirect(route('admin.sheets.eventos', $sheet));

        $player = Player::query()->where('first_name', 'Lautaro')->first() ?? Player::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.sheets.eventos.store', $sheet), [
                'type' => 'goal',
                'player_id' => $player->id,
                'minute' => 12,
                'detail' => 'Gol de prueba',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('match_sheet_events', [
            'match_sheet_id' => $sheet->id,
            'type' => 'goal',
        ]);

        $this->actingAs($user)
            ->get(route('admin.sheets.cierre', $sheet))
            ->assertOk()
            ->assertSee('Revisión y Cierre');

        $this->actingAs($user)
            ->post(route('admin.sheets.publish', $sheet))
            ->assertRedirect();

        $this->assertDatabaseHas('match_sheets', [
            'id' => $sheet->id,
            'status' => 'closed',
            'published' => 1,
        ]);

        $floripa = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $floripaCategory = Category::create([
            'tournament_id' => $floripa->id,
            'name' => 'Sub 16 Planillas Floripa',
            'birth_year' => '2010',
            'format' => 'Fútbol 11',
            'modality' => 'Fútbol 11',
            'branch' => 'Masculina',
            'status' => 'active',
        ]);
        $floripaHome = Team::create([
            'tournament_id' => $floripa->id,
            'category_id' => $floripaCategory->id,
            'name' => 'Avaí Planillas',
            'delegation_name' => 'Santa Catarina',
            'city' => 'Florianópolis',
            'status' => 'approved',
        ]);
        $floripaAway = Team::create([
            'tournament_id' => $floripa->id,
            'category_id' => $floripaCategory->id,
            'name' => 'Figueirense Planillas',
            'delegation_name' => 'Santa Catarina',
            'city' => 'Florianópolis',
            'status' => 'approved',
        ]);
        $floripaVenue = Venue::create([
            'tournament_id' => $floripa->id,
            'name' => 'Sede Planillas Floripa',
            'city' => 'Florianópolis',
            'status' => 'active',
        ]);
        $floripaField = Field::create([
            'venue_id' => $floripaVenue->id,
            'name' => 'Cancha Planillas Floripa',
            'status' => 'available',
        ]);
        $foreignMatch = FixtureMatch::create([
            'tournament_id' => $floripa->id,
            'category_id' => $floripaCategory->id,
            'field_id' => $floripaField->id,
            'home_team_id' => $floripaHome->id,
            'away_team_id' => $floripaAway->id,
            'scheduled_at' => '2027-01-21 11:00:00',
            'stage' => 'Grupo A',
            'status' => 'scheduled',
            'duration_minutes' => 70,
        ]);
        $foreignSheet = MatchSheet::create([
            'match_id' => $foreignMatch->id,
            'status' => 'draft',
            'validation_status' => 'pending',
            'current_step' => 1,
        ]);

        $tournamentAdmin = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $this->actingAs($tournamentAdmin)
            ->get(route('admin.sheets.index'))
            ->assertOk()
            ->assertSee('San Lorenzo vs Pampero')
            ->assertDontSee('Avaí Planillas');

        $this->actingAs($tournamentAdmin)
            ->get(route('admin.sheets.datos', $foreignSheet))
            ->assertForbidden();
    }

    public function test_results_module_uses_live_match_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.results.index'))
            ->assertOk()
            ->assertSee('Resultados oficiales')
            ->assertSee('San Lorenzo vs Pampero')
            ->assertSee('3 - 2')
            ->assertSee('Tablas')
            ->assertSee('Fair Play');

        $this->actingAs($user)
            ->get(route('admin.results.standings', ['category_id' => $category->id]))
            ->assertOk()
            ->assertSee('GRUPO B')
            ->assertSee('BUEN AYRE')
            ->assertDontSee('José Eduardo Oliveira Freitas');

        $this->actingAs($user)
            ->get(route('admin.results.brackets', ['category_id' => $category->id]))
            ->assertOk()
            ->assertSee('Final Oro')
            ->assertSee('San Lorenzo')
            ->assertSee('Pampero');

        $this->actingAs($user)
            ->get(route('admin.results.fairplay', ['category_id' => $category->id]))
            ->assertOk()
            ->assertSee('Pampero')
            ->assertSee('Pts FP');

        $this->actingAs($user)
            ->get(route('admin.results.rankings', ['category_id' => $category->id]))
            ->assertOk()
            ->assertSee('Thiago Martínez')
            ->assertSee('Santiago Gómez')
            ->assertSee('Lucas Pérez')
            ->assertSee('Vallas menos vencidas');

        $this->actingAs($user)
            ->get(route('admin.categories.competition', $category))
            ->assertOk()
            ->assertSee('Thiago Martínez')
            ->assertDontSee('José Eduardo Oliveira Freitas');

        $this->actingAs($user)
            ->get(route('admin.categories.rankings', $category))
            ->assertOk()
            ->assertSee('Thiago Martínez')
            ->assertDontSee('Lautaro Giménez');

        $match = FixtureMatch::query()
            ->where('status', 'finished')
            ->where('published', true)
            ->firstOrFail();
        $match->update(['published' => false]);

        $this->actingAs($user)
            ->patch(route('admin.results.publish.match', $match))
            ->assertRedirect();

        $this->assertDatabaseHas('matches', [
            'id' => $match->id,
            'published' => 1,
        ]);

        $this->actingAs($user)
            ->patch(route('admin.results.observe', $match))
            ->assertRedirect();

        $this->assertDatabaseHas('matches', [
            'id' => $match->id,
            'published' => 0,
        ]);

        $floripa = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $floripaCategory = Category::create([
            'tournament_id' => $floripa->id,
            'name' => 'Sub 16 Resultados Floripa',
            'birth_year' => '2010',
            'format' => 'Fútbol 11',
            'modality' => 'Fútbol 11',
            'branch' => 'Masculina',
            'status' => 'active',
        ]);
        $floripaHome = Team::create([
            'tournament_id' => $floripa->id,
            'category_id' => $floripaCategory->id,
            'name' => 'Avaí Resultados',
            'delegation_name' => 'Santa Catarina',
            'city' => 'Florianópolis',
            'status' => 'approved',
        ]);
        $floripaAway = Team::create([
            'tournament_id' => $floripa->id,
            'category_id' => $floripaCategory->id,
            'name' => 'Figueirense Resultados',
            'delegation_name' => 'Santa Catarina',
            'city' => 'Florianópolis',
            'status' => 'approved',
        ]);
        $floripaVenue = Venue::create([
            'tournament_id' => $floripa->id,
            'name' => 'Sede Resultados Floripa',
            'city' => 'Florianópolis',
            'status' => 'active',
        ]);
        $floripaField = Field::create([
            'venue_id' => $floripaVenue->id,
            'name' => 'Cancha Resultados Floripa',
            'status' => 'available',
        ]);
        $foreignMatch = FixtureMatch::create([
            'tournament_id' => $floripa->id,
            'category_id' => $floripaCategory->id,
            'field_id' => $floripaField->id,
            'home_team_id' => $floripaHome->id,
            'away_team_id' => $floripaAway->id,
            'scheduled_at' => '2027-01-20 16:00:00',
            'stage' => 'Final',
            'round' => 'Finales',
            'status' => 'finished',
            'home_score' => 4,
            'away_score' => 1,
            'duration_minutes' => 70,
            'published' => true,
        ]);

        $tournamentAdmin = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $this->actingAs($tournamentAdmin)
            ->get(route('admin.results.index'))
            ->assertOk()
            ->assertSee('San Lorenzo vs Pampero')
            ->assertDontSee('Avaí Resultados');

        $this->actingAs($tournamentAdmin)
            ->get(route('admin.results.standings', ['category_id' => $floripaCategory->id]))
            ->assertForbidden();

        $this->actingAs($tournamentAdmin)
            ->patch(route('admin.results.publish.match', $foreignMatch))
            ->assertForbidden();
    }

    public function test_communications_and_audit_modules_work(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'admin@stctorneos.demo')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.communications.index'))
            ->assertOk()
            ->assertSee('Contenido oficial')
            ->assertSee('Fixture de Final Oro confirmado')
            ->assertSee('Reglamento de Fair Play')
            ->assertSee('Notificaciones')
            ->assertSee('Favoritos');

        $this->actingAs($user)
            ->get(route('admin.communications.create'))
            ->assertOk()
            ->assertSee('Guardar contenido');

        $this->actingAs($user)
            ->post(route('admin.communications.store'), [
                'title' => 'Horario de acreditaciones',
                'type' => 'announcement',
                'summary' => 'Las acreditaciones abren a las 07:30.',
                'body' => 'Delegados: acercarse a Mesa Central con DNI.',
                'audience' => 'delegates',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $created = ContentPost::where('title', 'Horario de acreditaciones')->firstOrFail();

        $this->actingAs($user)
            ->patch(route('admin.communications.status', $created), ['status' => 'published'])
            ->assertRedirect();

        $this->assertDatabaseHas('content_posts', [
            'id' => $created->id,
            'status' => 'published',
        ]);

        $this->actingAs($user)
            ->get(route('admin.communications.notifications'))
            ->assertOk()
            ->assertSee('Resultado publicado: San Lorenzo 3-2 Pampero')
            ->assertSee('Recordatorio jornada 15/12');

        $draft = AppNotification::where('status', 'draft')->firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.communications.notifications.send', $draft))
            ->assertRedirect(route('admin.communications.notifications.show', $draft));

        $this->assertDatabaseHas('app_notifications', [
            'id' => $draft->id,
            'status' => 'sent',
        ]);

        $this->actingAs($user)
            ->get(route('admin.communications.favorites'))
            ->assertOk()
            ->assertSee('Leones FC')
            ->assertSee('San Lorenzo vs Pampero');

        $this->actingAs($user)
            ->get(route('admin.communications.plaques'))
            ->assertOk()
            ->assertSee('Placa Final Oro Sub 12');

        $this->actingAs($user)
            ->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('Historial de cambios')
            ->assertSee('Carga inicial de roles');

        $log = AuditLog::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.audit.show', $log))
            ->assertOk()
            ->assertSee($log->description);

        $floripa = Tournament::where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $foreignPost = ContentPost::create([
            'tournament_id' => $floripa->id,
            'author_id' => $user->id,
            'type' => 'announcement',
            'title' => 'Aviso Floripa Costa',
            'slug' => 'aviso-floripa-costa',
            'summary' => 'Comunicado solo de Floripa.',
            'audience' => 'public',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $tournamentAdmin = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $this->actingAs($tournamentAdmin)
            ->get(route('admin.communications.index'))
            ->assertOk()
            ->assertSee('Fixture de Final Oro confirmado')
            ->assertDontSee('Aviso Floripa Costa');

        $this->actingAs($tournamentAdmin)
            ->get(route('admin.communications.show', $foreignPost))
            ->assertForbidden();
    }

    public function test_permission_middleware_hides_and_blocks_modules(): void
    {
        $this->seed(DatabaseSeeder::class);

        $delegate = User::where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $referee = User::where('email', 'arbitro@stctorneos.demo')->firstOrFail();
        $playerUser = User::where('email', 'jugador@stctorneos.demo')->firstOrFail();
        $tournamentAdmin = User::where('email', 'torneo@stctorneos.demo')->firstOrFail();
        $superAdmin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();

        $this->actingAs($delegate)
            ->get(route('admin.players.index'))
            ->assertRedirect(route('workspace.home'));

        $this->actingAs($delegate)
            ->get('/dashboard')
            ->assertRedirect(route('workspace.home'));

        $this->actingAs($delegate)
            ->get(route('admin.users.index'))
            ->assertRedirect(route('workspace.home'));

        $this->actingAs($referee)
            ->get(route('admin.sheets.index'))
            ->assertOk()
            ->assertSee('Planillas');

        $this->actingAs($referee)
            ->get(route('admin.results.index'))
            ->assertOk();

        $this->actingAs($referee)
            ->get(route('admin.communications.index'))
            ->assertOk()
            ->assertSee('App y avisos');

        $this->actingAs($playerUser)
            ->get(route('workspace.player.home'))
            ->assertOk()
            ->assertSee('Mi ficha')
            ->assertSee('Lautaro Ruiz');

        $category = $playerUser->playerCategory();
        $this->assertNotNull($category);

        $this->actingAs($playerUser)
            ->get(route('workspace.categories.standings', $category))
            ->assertOk()
            ->assertSee('Clasificación');

        $this->actingAs($playerUser)
            ->get(route('workspace.categories.fixture', $category))
            ->assertOk();

        $this->actingAs($playerUser)
            ->get(route('workspace.categories.rankings', $category))
            ->assertOk()
            ->assertSee('Rankings');

        $this->actingAs($playerUser)
            ->get(route('admin.audit.index'))
            ->assertRedirect(route('workspace.home'));

        $this->actingAs($playerUser)
            ->get(route('admin.fixture.index'))
            ->assertRedirect(route('workspace.home'));

        $this->actingAs($tournamentAdmin)
            ->get(route('admin.tournaments.index'))
            ->assertOk();

        $this->actingAs($tournamentAdmin)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $lucia = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $this->actingAs($lucia)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Lucía Gómez')
            ->assertSee('Admin Torneo')
            ->assertSee('Santa Teresita Cup 2026')
            ->assertSee('/admin/fixture', false)
            ->assertDontSee('/admin/usuarios', false);

        $assistant = User::where('email', 'coordinador@stctorneos.demo')->firstOrFail();
        $this->actingAs($assistant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Asistente/árbitro')
            ->assertSee('/admin/fixture', false)
            ->assertSee('/admin/planillas', false)
            ->assertDontSee('/admin/delegaciones', false)
            ->assertDontSee('/admin/usuarios', false);

        $this->actingAs($referee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Asistente/árbitro')
            ->assertSee('/admin/planillas', false)
            ->assertSee('/admin/fixture', false);

        $this->actingAs($delegate)
            ->get(route('dashboard'))
            ->assertRedirect(route('workspace.home'));

        $this->actingAs($playerUser)
            ->get(route('dashboard'))
            ->assertRedirect(route('workspace.home'));

        $this->actingAs($superAdmin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Usuarios y Roles')
            ->assertSee('Lautaro Ruiz')
            ->assertSee('Diego Ruiz')
            ->assertSee('Carla Pérez');
    }

    public function test_dashboard_actions_respect_admin_scope(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();
        $tournamentAdmin = User::where('email', 'lucia@stctorneos.demo')->firstOrFail();
        $floripa = \App\Models\Tournament::query()->where('slug', 'stc-buenos-aires-2026')->firstOrFail();

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Todos los torneos')
            ->assertSee('Fichas pendientes')
            ->assertSee('Documentación pendiente')
            ->assertSee('Partido en vivo')
            ->assertSee('Jornada')
            ->assertSee('Fixture')
            ->assertSee('Resultados')
            ->assertSee('STC Buenos Aires 2026');

        $this->actingAs($tournamentAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Santa Teresita Cup 2026')
            ->assertSee('Admin Torneo · solo torneos asignados')
            ->assertSee('Fichas pendientes')
            ->assertSee('Fixture')
            ->assertSee('Resultados')
            ->assertDontSee('Todos los torneos')
            ->assertDontSee('STC Buenos Aires 2026');

        $this->actingAs($tournamentAdmin)
            ->get(route('dashboard', ['tournament_id' => $floripa->id]))
            ->assertOk()
            ->assertSee('Santa Teresita Cup 2026')
            ->assertDontSee('STC Buenos Aires 2026');

        $this->actingAs($tournamentAdmin)
            ->get(route('admin.tournaments.show', $floripa))
            ->assertForbidden();
    }

    public function test_topbar_search_works_on_every_admin_screen(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'admin@stctorneos.demo')->firstOrFail();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Buscar en esta pantalla')
            ->assertSee('>Buscar</button>', false)
            ->assertSee('action="'.route('admin.search').'"', false)
            ->assertSee('data-suggest-url="'.route('admin.search.suggest').'"', false);

        $this->actingAs($user)
            ->get(route('dashboard', ['search' => 'Pampero']))
            ->assertRedirect(route('admin.search', ['search' => 'Pampero']));

        $this->actingAs($user)
            ->get(route('admin.search', ['search' => 'Buenos Aires']))
            ->assertOk()
            ->assertSee('STC Buenos Aires 2026')
            ->assertSee('Torneos');

        $this->actingAs($user)
            ->get(route('admin.search', ['search' => 'Martínez']))
            ->assertOk()
            ->assertSee('Thiago Martínez');

        $this->actingAs($user)
            ->get(route('admin.players.index', ['search' => 'Pampero']))
            ->assertOk()
            ->assertSee('Mateo')
            ->assertDontSee('Thiago Martínez');

        $this->actingAs($user)
            ->get(route('admin.players.create'))
            ->assertOk()
            ->assertSee('action="'.route('admin.players.index').'"', false)
            ->assertSee('>Buscar</button>', false);

        $this->actingAs($user)
            ->get(route('admin.fixture.index'))
            ->assertOk()
            ->assertSee('action="'.route('admin.fixture.index').'"', false);

        $this->actingAs($user)
            ->get(route('admin.communications.favorites', ['search' => 'Pampero']))
            ->assertOk()
            ->assertSee('Pampero');

        $this->actingAs($user)
            ->get(route('admin.results.standings', ['search' => 'San Lorenzo']))
            ->assertOk()
            ->assertSee('SAN LORENZO');

        $this->actingAs($user)
            ->getJson(route('admin.search.suggest', ['q' => 'Mar']))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Thiago Martínez', 'group' => 'Jugadores']);

        $this->actingAs($user)
            ->getJson(route('admin.search.suggest', ['q' => 'thiago mar']))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Thiago Martínez']);

        $this->actingAs($user)
            ->getJson(route('admin.search.suggest', ['q' => 'Buenos Aires', 'scope' => 'Torneos']))
            ->assertOk()
            ->assertJsonFragment(['title' => 'STC Buenos Aires 2026', 'group' => 'Torneos']);
    }
}
