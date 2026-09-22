<?php

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\AppNotificationRecipient;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\ContentPost;
use App\Models\Delegation;
use App\Models\FavoriteMatch;
use App\Models\FavoriteTeam;
use App\Models\Field;
use App\Models\FixtureMatch;
use App\Models\Guardian;
use App\Models\Invitation;
use App\Models\MatchSheet;
use App\Models\MatchSheetEvent;
use App\Models\MatchSheetIncident;
use App\Models\Permission;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamStaff;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissions = collect([
            ['Sistema', 'Ver dashboard', 'dashboard.view'],
            ['Sistema', 'Administrar usuarios', 'users.manage'],
            ['Sistema', 'Administrar roles y permisos', 'roles.manage'],
            ['Torneos', 'Gestionar torneos', 'tournaments.manage'],
            ['Delegaciones', 'Gestionar delegaciones', 'delegations.manage'],
            ['Jugadores', 'Aprobar fichas y documentos', 'players.approve'],
            ['Fixture', 'Gestionar fixture y partidos', 'matches.manage'],
            ['Planillas', 'Cargar y cerrar planillas', 'match_sheets.manage'],
            ['Comunicaciones', 'Enviar notificaciones', 'communications.manage'],
            ['Auditoria', 'Consultar auditoria', 'audit.view'],
        ])->mapWithKeys(function (array $permission) {
            [$group, $name, $slug] = $permission;

            return [
                $slug => Permission::updateOrCreate(
                    ['slug' => $slug],
                    ['group' => $group, 'name' => $name]
                ),
            ];
        });

        $roles = collect([
            ['Super Admin', 'super-admin', 'system', '#00d5ff', 'Control total del sistema STC.'],
            ['Admin Torneo', 'admin-torneo', 'tournament', '#006bff', 'Gestiona torneos, sedes, equipos y competencia.'],
            ['Coordinador', 'coordinador', 'tournament', '#2eff94', 'Supervisa fixture, planillas y operación.'],
            ['Delegado', 'delegado', 'delegation', '#ffc84d', 'Administra equipos, jugadores y documentación.'],
            ['Arbitro', 'arbitro', 'match', '#ff7a18', 'Carga eventos, disciplina e informe arbitral.'],
            ['Asistente / Mesa', 'asistente-mesa', 'match', '#9d7cff', 'Apoya operación de partidos y planillas.'],
            ['Consulta', 'consulta', 'system', '#8290a7', 'Acceso de solo lectura.'],
        ])->mapWithKeys(function (array $role) {
            [$name, $slug, $scope, $color, $description] = $role;

            return [
                $slug => Role::updateOrCreate(
                    ['slug' => $slug],
                    compact('name', 'scope', 'color', 'description')
                ),
            ];
        });

        $rolePermissions = [
            'super-admin' => $permissions->keys()->all(),
            'admin-torneo' => ['dashboard.view', 'tournaments.manage', 'delegations.manage', 'players.approve', 'matches.manage', 'match_sheets.manage', 'communications.manage', 'audit.view'],
            'coordinador' => ['dashboard.view', 'matches.manage', 'match_sheets.manage', 'communications.manage'],
            'delegado' => ['dashboard.view', 'delegations.manage', 'players.approve'],
            'arbitro' => ['dashboard.view', 'match_sheets.manage'],
            'asistente-mesa' => ['dashboard.view', 'match_sheets.manage'],
            'consulta' => ['dashboard.view', 'audit.view'],
        ];

        foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
            $roles[$roleSlug]->permissions()->sync(
                $permissions->only($permissionSlugs)->pluck('id')->all()
            );
        }

        $users = collect([
            ['Admin General STC', 'admin@stctorneos.demo', 'Super Admin', 'super-admin', 'Torneo completo', 'active'],
            ['Valeria Gómez', 'torneo@stctorneos.demo', 'Admin Torneo', 'admin-torneo', 'Santa Teresita Cup 2026', 'active'],
            ['Lucía Gómez', 'lucia@stctorneos.demo', 'Admin Torneo', 'admin-torneo', 'Santa Teresita Cup 2026', 'active'],
            ['Martín Sosa', 'arbitro@stctorneos.demo', 'Arbitro', 'arbitro', 'Final Oro', 'active'],
            ['Sebastián Martínez', 'delegado@stctorneos.demo', 'Delegado', 'delegado', 'Leones 2014', 'active'],
            ['Nora Álvarez', 'consulta@stctorneos.demo', 'Consulta', 'consulta', 'Solo lectura', 'active'],
            ['Diego Ruiz', 'coordinador@stctorneos.demo', 'Coordinador', 'coordinador', 'Santa Teresita Cup 2026', 'active'],
            ['Carla Pérez', 'mesa@stctorneos.demo', 'Asistente / Mesa', 'asistente-mesa', 'Cancha 3', 'active'],
        ])->map(function (array $data) use ($roles) {
            [$name, $email, $roleName, $roleSlug, $scope, $status] = $data;

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'phone' => '+54 9 11 '.sprintf('%04d-%04d', random_int(1000, 9999), random_int(1000, 9999)),
                    'password' => Hash::make('stcdemo'),
                    'status' => $status,
                    'current_scope' => $scope,
                    'email_verified_at' => $status === 'active' ? now() : null,
                ]
            );

            $user->roles()->syncWithoutDetaching([
                $roles[$roleSlug]->id => [
                    'assigned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            return [$roleName, $user];
        });

        Invitation::updateOrCreate(
            ['email' => 'nuevo.delegado@clubdemo.com'],
            [
                'name' => 'Nuevo Delegado Club Demo',
                'role_id' => $roles['delegado']->id,
                'status' => 'pending',
                'token' => Str::random(40),
                'scope_type' => 'delegation',
                'expires_at' => now()->addDays(7),
                'invited_by' => User::where('email', 'admin@stctorneos.demo')->value('id'),
            ]
        );

        $tournament = Tournament::updateOrCreate(
            ['slug' => 'santa-teresita-cup-2026'],
            [
                'name' => 'Santa Teresita Cup 2026',
                'edition' => 'Edición 2026',
                'logo_path' => 'images/stc-logo.png',
                'country' => 'Argentina',
                'city' => 'Santa Teresita',
                'venue_name' => 'Complejo Deportivo Santa Teresita',
                'timezone' => 'America/Argentina/Buenos_Aires',
                'location' => 'Santa Teresita, Buenos Aires',
                'starts_at' => '2026-12-14',
                'ends_at' => '2026-12-20',
                'status' => 'in_progress',
                'description' => 'Torneo internacional de fútbol infantil con operación web y app móvil STC.',
                'contact_name' => 'Mesa Central STC',
                'contact_email' => 'operacion@stctorneos.demo',
                'contact_phone' => '+54 9 11 5555-2026',
                'rules_url' => 'https://stctorneos.demo/reglamento',
                'general_info' => 'Operación demo conectada a módulos de categorías, equipos, fixture y planillas.',
                'visibility' => 'public',
                'registration_starts_at' => '2026-08-01',
                'registration_ends_at' => '2026-11-30',
            ]
        );

        collect([
            [
                'slug' => 'stc-buenos-aires-2026',
                'name' => 'STC Buenos Aires 2026',
                'edition' => 'Edición Buenos Aires',
                'country' => 'Argentina',
                'city' => 'CABA',
                'venue_name' => 'Predio Deportivo Sur',
                'location' => 'CABA, Argentina',
                'starts_at' => '2026-07-20',
                'ends_at' => '2026-07-24',
                'status' => 'finished',
                'visibility' => 'public',
                'registration_starts_at' => '2026-03-01',
                'registration_ends_at' => '2026-06-30',
            ],
        ])->each(function (array $data): void {
            Tournament::updateOrCreate(
                ['slug' => $data['slug']],
                $data + [
                    'logo_path' => 'images/stc-logo.png',
                    'timezone' => 'America/Argentina/Buenos_Aires',
                    'description' => 'Torneo demo para validar filtros y estados del Admin Web.',
                    'contact_name' => 'Mesa Central STC',
                    'contact_email' => 'operacion@stctorneos.demo',
                    'contact_phone' => '+54 9 11 5555-2026',
                    'rules_url' => 'https://stctorneos.demo/reglamento',
                    'general_info' => 'Datos de demostración conectados a MySQL para presentación del flujo.',
                ]
            );
        });

        $stcId = Tournament::query()->where('slug', 'santa-teresita-cup-2026')->value('id');
        $buenosAiresId = Tournament::query()->where('slug', 'stc-buenos-aires-2026')->value('id');

        foreach ([
            'admin@stctorneos.demo' => [$stcId, $buenosAiresId],
            'torneo@stctorneos.demo' => [$stcId, $buenosAiresId],
            'lucia@stctorneos.demo' => [$stcId, null],
            'arbitro@stctorneos.demo' => [$stcId, null],
            'delegado@stctorneos.demo' => [$stcId, null],
            'coordinador@stctorneos.demo' => [$stcId, null],
            'mesa@stctorneos.demo' => [$stcId, null],
        ] as $email => [$tournamentId, $extraId]) {
            User::query()->where('email', $email)->update([
                'tournament_id' => $tournamentId,
                'extra_tournament_id' => $extraId,
            ]);
        }

        $categoryRows = [
            ['Categoría 2005/2006', '2005/2006', 'Masculina', 'Fútbol 11', 'Fase de grupos + eliminatoria', 24, 11, 22, 11, 7],
            ['Categoría 2007', '2007', 'Masculina', 'Fútbol 11', 'Grupos y finales', 24, 11, 22, 11, 7],
            ['Categoría 2008', '2008', 'Masculina', 'Fútbol 11', 'Grupos y finales', 24, 11, 22, 11, 7],
            ['Categoría 2009', '2009', 'Masculina', 'Fútbol 9', 'Grupos y finales', 20, 9, 18, 9, 6],
            ['Categoría 2010', '2010', 'Masculina', 'Fútbol 9', 'Grupos y finales', 20, 9, 18, 9, 6],
            ['Categoría 2011', '2011', 'Masculina', 'Fútbol 8', 'Grupos y finales', 18, 8, 16, 8, 5],
            ['Categoría 2012', '2012', 'Masculina', 'Fútbol 8', 'Grupos y finales', 18, 8, 16, 8, 5],
            ['Categoría 2013', '2013', 'Masculina', 'Fútbol 7', 'Grupos y Oro', 16, 7, 14, 7, 5],
            ['Categoría 2014', '2014', 'Masculina', 'Fútbol 7', 'Grupos y Oro', 16, 7, 14, 7, 5],
            ['Categoría 2015', '2015', 'Masculina', 'Fútbol 7', 'Grupos y finales', 16, 7, 14, 7, 5],
            ['Categoría 2016', '2016', 'Mixta', 'Fútbol 6', 'Grupos y finales', 16, 6, 12, 6, 4],
            ['Sub 8 Masculino', '2018', 'Masculina', 'Fútbol 5', 'Grupos y finales', 16, 5, 10, 5, 3],
            ['Sub 9 Masculino', '2017', 'Masculina', 'Fútbol 5', 'Grupos y finales', 16, 5, 10, 5, 3],
            ['Sub 10 Mixto', '2016', 'Mixta', 'Fútbol 7', 'Grupos, semifinales y final', 32, 7, 16, 7, 5],
            ['Sub 11 Femenino', '2015', 'Femenina', 'Fútbol 7', 'Grupos y finales', 16, 7, 14, 7, 5],
            ['Fem Sub 12', '2014', 'Femenina', 'Fútbol 7', 'Grupos y finales', 16, 7, 14, 7, 5],
            ['Sub 12 Masculino', '2014', 'Masculina', 'Fútbol 11', 'Grupos y finales', 48, 11, 22, 11, 7],
            ['Sub 13 Femenino', '2013', 'Femenina', 'Fútbol 7', 'Grupos y finales', 16, 7, 14, 7, 5],
            ['Sub 14 Masculino', '2012', 'Masculina', 'Fútbol 11', 'Eliminación directa', 24, 11, 22, 11, 7],
            ['Sub 15 Masculino', '2011', 'Masculina', 'Fútbol 11', 'Grupos y finales', 24, 11, 22, 11, 7],
            ['Sub 16 Femenino', '2010', 'Femenina', 'Fútbol 9', 'Grupos y finales', 20, 9, 18, 9, 6],
            ['Escuelitas Mixto', '2019', 'Mixta', 'Fútbol 5', 'Grupos y finales', 16, 5, 10, 5, 3],
            ['Veteranos +35', '1989/1990', 'Masculina', 'Fútbol 11', 'Grupos y finales', 16, 11, 22, 11, 7],
        ];

        $categories = collect($categoryRows)->mapWithKeys(function (array $category, int $index) use ($tournament) {
            [$name, $birthYear, $branch, $modality, $competitionFormat, $teamLimit, $minPlayers, $maxPlayers, $playersOnField, $substitutes] = $category;

            return [
                $name => Category::updateOrCreate(
                    ['tournament_id' => $tournament->id, 'name' => $name],
                    [
                        'birth_year' => $birthYear,
                        'branch' => $branch,
                        'modality' => $modality,
                        'format' => $modality,
                        'image_path' => 'images/category-banner.svg',
                        'team_limit' => $teamLimit,
                        'min_players' => $minPlayers,
                        'max_players' => $maxPlayers,
                        'players_on_field' => $playersOnField,
                        'substitutes' => $substitutes,
                        'periods' => 2,
                        'period_duration' => $playersOnField === 11 ? 25 : 20,
                        'competition_format' => $competitionFormat,
                        'groups_count' => $competitionFormat === 'Eliminación directa' ? 0 : 4,
                        'teams_per_group' => $competitionFormat === 'Eliminación directa' ? 0 : 4,
                        'qualifiers_count' => $competitionFormat === 'Eliminación directa' ? 16 : 8,
                        'phases' => $competitionFormat === 'Eliminación directa'
                            ? 'Octavos, cuartos, semifinal y final'
                            : 'Fase de grupos y finales',
                        'brackets' => $competitionFormat === 'Eliminación directa'
                            ? 'Llave de 16 equipos. El 1° cruza con el 16°, el 2° con el 15°.'
                            : 'Los primeros de cada grupo cruzan con los segundos de otra zona.',
                        'classification_criteria' => 'Clasifican los primeros de cada grupo y los mejores terceros según puntos, diferencia de gol y fair play.',
                        'points_win' => 3,
                        'points_draw' => 1,
                        'points_loss' => 0,
                        'tiebreakers' => ['Diferencia de gol', 'Goles a favor', 'Fair Play', 'Sorteo'],
                        'rules' => 'Reglas STC estándar para '.$modality.'. Cambios permitidos según reglamento del torneo.',
                        'discipline_rules' => 'Amarillas, rojas y sanciones impactan Fair Play y habilitación según resolución administrativa.',
                        'status' => 'active',
                        'sort_order' => $index + 1,
                    ]
                ),
            ];
        });

        Venue::query()->where('name', 'Club Norte')->update(['name' => 'Complejo Norte']);

        $venues = collect([
            ['Complejo Deportivo Santa Teresita', 'Santa Teresita', 'Av. Costanera y Calle 41', 'active', null],
            ['Complejo Norte', 'Santa Teresita', 'Av. Libertador 1200', 'active', null],
            ['Polideportivo STC', 'Santa Teresita', 'Av. Costanera 1200', 'active', 'Acceso por portón lateral · estacionamiento limitado'],
            ['Polideportivo Sur', 'Santa Teresita', 'Calle 18 y Costanera', 'active', null],
        ])->mapWithKeys(function (array $venue) use ($tournament) {
            [$name, $city, $address, $status, $notes] = $venue;

            return [
                $name => Venue::updateOrCreate(
                    ['name' => $name],
                    [
                        'tournament_id' => $tournament->id,
                        'city' => $city,
                        'address' => $address,
                        'map_url' => 'https://www.openstreetmap.org/search?query='.urlencode($address.', '.$city),
                        'status' => $status,
                        'notes' => $notes,
                    ]
                ),
            ];
        });

        $fields = collect([
            ['Complejo Deportivo Santa Teresita', 'Cancha 1', 'available', '08:00', '20:00', 'Fútbol 11', null],
            ['Complejo Deportivo Santa Teresita', 'Cancha 2', 'available', '08:00', '20:00', 'Fútbol 11', null],
            ['Complejo Deportivo Santa Teresita', 'Cancha 3', 'available', '08:00', '20:00', 'Fútbol 7', null],
            ['Complejo Deportivo Santa Teresita', 'Cancha 4', 'available', '08:00', '20:00', 'Fútbol 7', null],
            ['Complejo Norte', 'Cancha 1', 'available', '08:00', '22:00', 'Fútbol 11', null],
            ['Complejo Norte', 'Cancha 2', 'maintenance', '08:00', '22:00', 'Fútbol 7', null],
            ['Polideportivo STC', 'Cancha 3', 'occupied', '10:00', '18:00', 'Fútbol 7', 'Acceso por portón lateral · estacionamiento limitado'],
            ['Polideportivo Sur', 'Cancha A', 'available', '09:00', '21:00', 'Fútbol 11', null],
            ['Polideportivo Sur', 'Cancha B', 'available', '09:00', '21:00', 'Fútbol 7', null],
            ['Complejo Norte', 'Cancha 3', 'available', '08:00', '22:00', 'Fútbol 7', 'Césped nuevo · ideal Sub 12'],
            ['Complejo Deportivo Santa Teresita', 'Cancha 5', 'available', '08:00', '20:00', 'Fútbol 5', 'Escuelitas y formatos chicos'],
            ['Complejo Deportivo Santa Teresita', 'Cancha 6', 'occupied', '10:00', '18:00', 'Fútbol 9', 'Reservada para fase de grupos'],
        ])->mapWithKeys(function (array $field) use ($venues) {
            [$venueName, $name, $status, $opens, $closes, $modality, $notes] = $field;

            return [
                $venueName.'|'.$name => Field::updateOrCreate(
                    ['venue_id' => $venues[$venueName]->id, 'name' => $name],
                    [
                        'surface' => 'Césped sintético',
                        'modality' => $modality,
                        'lighting' => true,
                        'opens_at' => $opens,
                        'closes_at' => $closes,
                        'status' => $status,
                        'notes' => $notes,
                    ]
                ),
            ];
        });

        $santaId = $venues['Complejo Deportivo Santa Teresita']->id;
        $fieldBySimpleName = $fields->filter(fn (Field $field) => (int) $field->venue_id === (int) $santaId)->keyBy('name');

        $delegations = collect([
            ['San Lorenzo', 'Argentina', 'Buenos Aires', 'Martín Sosa', 'aprobada' => 'approved'],
            ['Pampero', 'Argentina', 'Santa Fe', 'Carlos Pérez', 'aprobada' => 'pending'],
            ['Buen Ayre', 'Argentina', 'Córdoba', 'Sofía Ruiz', 'aprobada' => 'approved'],
            ['Leones', 'Argentina', 'Rosario', 'Sebastián Martínez', 'aprobada' => 'observed'],
            ['Zelaya FC', 'Argentina', 'Buenos Aires', 'Diego Núñez', 'aprobada' => 'approved'],
        ])->mapWithKeys(function (array $delegation) use ($tournament) {
            [$name, $country, $city, $delegateName] = $delegation;

            return [
                $name => Delegation::updateOrCreate(
                    ['tournament_id' => $tournament->id, 'name' => $name],
                    [
                        'country' => $country,
                        'city' => $city,
                        'logo_path' => 'images/stc-logo.png',
                        'delegate_name' => $delegateName,
                        'delegate_email' => Str::slug($delegateName, '.').'@club.demo',
                        'delegate_phone' => '+54 11 5555-'.random_int(1000, 9999),
                        'status' => $delegation['aprobada'],
                        'notes' => 'Delegación demo para validar inscripción, revisión y equipos vinculados.',
                    ]
                ),
            ];
        });

        $teams = collect([
            ['Pampero', 'Club Atlético Pampero', 'Santa Fe'],
            ['San Lorenzo', 'San Lorenzo de Almagro', 'CABA'],
            ['Leones FC', 'Leones del Sur', 'Buenos Aires'],
            ['Unión FC', 'Unión Fútbol Club', 'Mar del Plata'],
            ['Buen Ayre', 'Club Buen Ayre', 'Córdoba'],
            ['Zelaya', 'Deportivo Zelaya', 'Buenos Aires'],
            ['Atlético del Sur', 'Atlético del Sur', 'La Plata'],
            ['Deportivo Norte', 'Deportivo Norte', 'Rosario'],
        ])->mapWithKeys(function (array $team) use ($categories, $delegations, $tournament) {
            [$name, $delegation, $city] = $team;

            return [
                $name => Team::updateOrCreate(
                    ['tournament_id' => $tournament->id, 'name' => $name],
                    [
                        'category_id' => $categories['Sub 12 Masculino']->id,
                        'delegation_id' => $delegations->get($delegation)?->id,
                        'delegation_name' => $delegation,
                        'city' => $city,
                        'country_code' => 'AR',
                        'group_name' => in_array($name, ['Pampero', 'San Lorenzo', 'Leones FC', 'Unión FC'], true) ? 'A' : 'B',
                        'home_kit' => 'Azul / Rojo',
                        'away_kit' => 'Blanca',
                        'player_capacity' => 14,
                        'shield_path' => 'images/stc-logo.png',
                        'status' => 'approved',
                    ]
                ),
            ];
        });

        TeamStaff::updateOrCreate(
            ['team_id' => $teams['San Lorenzo']->id, 'role' => 'director_tecnico'],
            [
                'first_name' => 'Marcelo',
                'last_name' => 'Giménez',
                'document_number' => '28444666',
                'photo_path' => 'images/stc-logo.png',
                'status' => 'active',
            ]
        );

        TeamStaff::updateOrCreate(
            ['team_id' => $teams['San Lorenzo']->id, 'role' => 'preparador_fisico'],
            [
                'first_name' => 'Lucía',
                'last_name' => 'Benítez',
                'document_number' => '35111222',
                'photo_path' => 'images/stc-logo.png',
                'status' => 'active',
            ]
        );

        $matches = [
            ['Pampero', 'San Lorenzo', 'Cancha 3', '2026-12-14 09:00:00', 'Grupo A', 'live', 1, 2, '31:42'],
            ['Leones FC', 'Unión FC', 'Cancha 2', '2026-12-14 09:30:00', 'Grupo A', 'live', 0, 0, '12:00'],
            ['Buen Ayre', 'Zelaya', 'Cancha 1', '2026-12-14 08:00:00', 'Grupo B', 'finished', 1, 0, null],
            ['Zelaya', 'Unión FC', 'Cancha 3', '2026-12-14 09:10:00', 'Grupo B', 'scheduled', null, null, null],
            ['Atlético del Sur', 'Deportivo Norte', 'Cancha 4', '2026-12-14 10:30:00', 'Grupo B', 'scheduled', null, null, null],
            ['San Lorenzo', 'Leones FC', 'Cancha 3', '2026-12-14 12:00:00', 'Grupo A', 'scheduled', null, null, null],
            ['Unión FC', 'Pampero', 'Cancha 2', '2026-12-14 13:30:00', 'Grupo A', 'scheduled', null, null, null],
        ];

        foreach ($matches as $match) {
            [$home, $away, $field, $scheduledAt, $stage, $status, $homeScore, $awayScore, $minute] = $match;

            FixtureMatch::updateOrCreate(
                [
                    'tournament_id' => $tournament->id,
                    'home_team_id' => $teams[$home]->id,
                    'away_team_id' => $teams[$away]->id,
                    'scheduled_at' => $scheduledAt,
                ],
                [
                    'category_id' => $categories['Sub 12 Masculino']->id,
                    'field_id' => $fieldBySimpleName[$field]->id,
                    'stage' => $stage,
                    'round' => str_starts_with($stage, 'Grupo') ? 'Fecha 1' : 'Finales',
                    'status' => $status,
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                    'minute' => $minute,
                    'duration_minutes' => 70,
                    'published' => in_array($status, ['live', 'finished'], true),
                    'published_at' => in_array($status, ['live', 'finished'], true) ? now() : null,
                    'referee_name' => $status === 'live' ? 'Martín Sosa' : null,
                ]
            );
        }

        $demoCategoryTeams = [
            ['San Lorenzo', 'Buenos Aires', 'Buenos Aires'],
            ['River Plate', 'Capital Federal', 'CABA'],
            ['Boca Juniors', 'La Boca', 'CABA'],
            ['Racing Club', 'Avellaneda', 'Buenos Aires'],
            ['Palmeiras', 'Brasil', 'Sao Paulo'],
            ['Independiente', 'Avellaneda', 'Buenos Aires'],
            ['Cosmos FC', 'Rosario', 'Santa Fe'],
            ['Juventud', 'La Plata', 'Buenos Aires'],
        ];

        foreach ($categories as $categoryModel) {
            $categoryTeams = collect($demoCategoryTeams)->mapWithKeys(function (array $team) use ($categoryModel, $delegations, $tournament) {
                [$name, $delegation, $city] = $team;

                return [
                    $name => Team::updateOrCreate(
                        ['tournament_id' => $tournament->id, 'category_id' => $categoryModel->id, 'name' => $name],
                        [
                            'delegation_id' => $delegations->get($name)?->id,
                            'delegation_name' => $delegation,
                            'city' => $city,
                            'country_code' => $name === 'Palmeiras' ? 'BR' : 'AR',
                            'group_name' => in_array($name, ['San Lorenzo', 'River Plate', 'Boca Juniors', 'Racing Club'], true) ? 'A' : 'B',
                            'home_kit' => 'Azul / Rojo',
                            'away_kit' => 'Blanca',
                            'player_capacity' => $categoryModel->max_players ?: 14,
                            'shield_path' => 'images/stc-logo.png',
                            'status' => 'approved',
                        ]
                    ),
                ];
            });

            $demoMatches = [
                ['San Lorenzo', 'River Plate', 'Cancha 1', 'Grupo A', 'finished', 0, 0, '2026-12-17 10:00:00'],
                ['Racing Club', 'Boca Juniors', 'Cancha 2', 'Grupo A', 'finished', 0, 0, '2026-12-17 12:00:00'],
                ['Palmeiras', 'Cosmos FC', 'Cancha 3', 'Final bronce', 'finished', 2, 1, '2026-12-17 14:00:00'],
                ['San Lorenzo', 'Racing Club', 'Cancha 1', 'Semifinal', 'finished', 1, 1, '2026-12-17 16:00:00'],
            ];

            foreach ($demoMatches as $match) {
                [$home, $away, $field, $stage, $status, $homeScore, $awayScore, $scheduledAt] = $match;

                FixtureMatch::updateOrCreate(
                    [
                        'tournament_id' => $tournament->id,
                        'category_id' => $categoryModel->id,
                        'home_team_id' => $categoryTeams[$home]->id,
                        'away_team_id' => $categoryTeams[$away]->id,
                        'scheduled_at' => $scheduledAt,
                    ],
                    [
                        'field_id' => $fieldBySimpleName[$field]->id,
                        'stage' => $stage,
                        'round' => str_contains($stage, 'Final') || str_contains($stage, 'Semi') ? 'Finales' : 'Fecha 2',
                        'status' => $status,
                        'home_score' => $homeScore,
                        'away_score' => $awayScore,
                        'minute' => null,
                        'duration_minutes' => 70,
                        'published' => $status === 'finished',
                    ]
                );
            }
        }

        $norteField = $fields['Complejo Norte|Cancha 1'] ?? null;
        if ($norteField) {
            $sub14 = $categories['Sub 14 Masculino'];
            $sub12 = $categories['Sub 12 Masculino'];
            $teamIn = fn (Category $category, string $name) => Team::query()
                ->where('tournament_id', $tournament->id)
                ->where('category_id', $category->id)
                ->where('name', $name)
                ->firstOrFail();

            foreach ([
                [$sub14, 'San Lorenzo', 'Racing Club', '2026-08-15 10:00:00', 'scheduled', false],
                [$sub12, 'Boca Juniors', 'Independiente', '2026-08-15 12:30:00', 'scheduled', false],
                [$sub12, 'Independiente', 'River Plate', '2026-08-16 09:00:00', 'scheduled', true],
                [$sub14, 'River Plate', 'Racing Club', '2026-08-16 11:00:00', 'rescheduled', false],
            ] as $row) {
                [$category, $home, $away, $scheduledAt, $status, $published] = $row;

                FixtureMatch::updateOrCreate(
                    [
                        'tournament_id' => $tournament->id,
                        'category_id' => $category->id,
                        'home_team_id' => $teamIn($category, $home)->id,
                        'away_team_id' => $teamIn($category, $away)->id,
                        'scheduled_at' => $scheduledAt,
                    ],
                    [
                        'field_id' => $norteField->id,
                        'stage' => 'Fecha 1',
                        'round' => 'Fecha 1',
                        'status' => $status,
                        'duration_minutes' => 70,
                        'published' => $published,
                        'published_at' => $published ? now() : null,
                    ]
                );
            }
        }

        $demoPlayers = [
            ['Thiago', 'Martínez', 'San Lorenzo', 'enabled', 'Delantero', 10, '2014-03-12', '45678901'],
            ['Benjamín', 'Sosa', 'San Lorenzo', 'enabled', 'Arquero', 1, '2014-08-04', '48123459'],
            ['Santiago', 'Gómez', 'San Lorenzo', 'enabled', 'Mediocampista', 8, '2014-04-19', '49456789'],
            ['Benjamín', 'Ruiz', 'San Lorenzo', 'observed', 'Defensor', 4, '2014-07-11', '48345678'],
            ['Tomás', 'Fernández', 'San Lorenzo', 'approved', 'Delantero', 11, '2014-02-02', '47890123'],
            ['Agustín', 'Molina', 'San Lorenzo', 'pending', 'Mediocampista', 6, '2014-09-21', '47112233'],
            ['Mateo', 'Díaz', 'Pampero', 'pending', 'Mediocampista', 8, '2014-06-21', '48123457'],
            ['Lucas', 'Fernández', 'San Lorenzo', 'observed', 'Defensor', 5, '2014-03-08', '48123999'],
            ['Lucas', 'Pérez', 'Pampero', 'observed', 'Defensor', 4, '2014-01-18', '48123458'],
            ['Lautaro', 'Ruiz', 'Leones FC', 'approved', 'Delantero', 11, '2014-05-30', '48123460'],
            ['Franco', 'Núñez', 'Unión FC', 'draft', 'Mediocampista', 6, '2014-11-02', '48123461'],
            ['Valentín', 'Gómez', 'Buen Ayre', 'rejected', 'Defensor', 3, '2014-02-14', '48123462'],
            ['Santino', 'López', 'Zelaya', 'enabled', 'Delantero', 7, '2014-09-09', '48123463'],
        ];

        foreach ($demoPlayers as $playerData) {
            [$firstName, $lastName, $teamName, $status, $position, $jersey, $birthDate, $document] = $playerData;
            $team = $teams[$teamName] ?? $teams->first();
            $isThiago = $firstName === 'Thiago' && $lastName === 'Martínez';

            $player = Player::updateOrCreate(
                ['team_id' => $team->id, 'first_name' => $firstName, 'last_name' => $lastName],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'document_number' => $document,
                    'birth_date' => $isThiago ? '2014-04-12' : $birthDate,
                    'nationality' => 'Argentina',
                    'address' => $isThiago ? 'Av. La Plata 1234, CABA' : null,
                    'position' => $position,
                    'jersey_number' => $jersey,
                    'preferred_foot' => 'Derecha',
                    'height' => $isThiago ? '1.62 m' : null,
                    'weight' => $isThiago ? '52 kg' : null,
                    'blood_type' => $isThiago ? 'O+' : null,
                    'medical_coverage' => $isThiago ? 'OSDE 210' : null,
                    'allergies' => 'Ninguna',
                    'medication' => 'Ninguna',
                    'illnesses' => 'Ninguna',
                    'restrictions' => 'Ninguna',
                    'emergency_contact' => $isThiago ? 'María Martínez · +54 11 5555-1234' : null,
                    'photo_path' => 'images/players/thiago-martinez.png',
                    'status' => $isThiago ? 'pending' : $status,
                    'observation_reason' => $status === 'observed' ? 'Ficha médica vencida, falta renovación.' : null,
                    'notes' => $status === 'rejected' ? 'Documento ilegible. Pedir nueva foto del DNI.' : null,
                ]
            );

            Guardian::updateOrCreate(
                ['player_id' => $player->id],
                [
                    'name' => $isThiago ? 'María Martínez' : 'Familiar de '.$lastName,
                    'relationship' => $isThiago ? 'Madre' : 'Padre',
                    'email' => $isThiago ? 'maria@email.com' : Str::slug($lastName, '.').'.tutor@club.demo',
                    'phone' => $isThiago ? '+54 11 5555-1234' : '+54 11 5555-'.random_int(2000, 9999),
                    'alternate_contact' => $isThiago ? 'Carlos Martínez (padre) · +54 11 5555-5678' : null,
                    'consent_status' => in_array($status, ['enabled', 'approved'], true) ? 'approved' : 'pending',
                ]
            );

            $approvedDocs = match (true) {
                $isThiago => ['DNI', 'Apto médico', 'Cobertura médica', 'Aut. participación', 'Atención médica', 'Reglamento', 'Declaraciones'],
                in_array($status, ['enabled', 'approved'], true) => Player::documentTypes(),
                $status === 'observed' => ['DNI', 'Apto médico'],
                default => ['DNI'],
            };

            foreach (Player::documentTypes() as $type) {
                PlayerDocument::updateOrCreate(
                    ['player_id' => $player->id, 'type' => $type],
                    ['status' => in_array($type, $approvedDocs, true) ? 'approved' : 'pending']
                );
            }
        }

        $mateo = Player::where('first_name', 'Mateo')->where('last_name', 'Díaz')->first();
        $lucas = Player::where('first_name', 'Lucas')->where('last_name', 'Fernández')->first();
        $thiago = Player::where('first_name', 'Thiago')->where('last_name', 'Martínez')->first();
        $benjamin = Player::where('first_name', 'Benjamín')->where('last_name', 'Ruiz')->first();
        $santiago = Player::where('first_name', 'Santiago')->where('last_name', 'Gómez')->first();

        $figmaDocs = [
            [$mateo, 'Ficha médica', 'pending', 'ficha_medica_mateo_diaz.pdf', 'María Gómez (Padre/Tutor)', '2026-08-14', null, [
                'identity_match' => 'ok',
                'tutor_signed' => 'ok',
                'expiration_valid' => 'warn',
                'file_readable' => 'ok',
                'fit_to_play' => 'warn',
            ]],
            [$lucas, 'DNI frente', 'observed', 'dni_frente_lucas_fernandez.pdf', 'Familiar de Fernández (Padre)', '2026-08-10', 'La foto del DNI está cortada. Pedir toma nítida del frente.', [
                'identity_match' => 'warn',
                'tutor_signed' => 'ok',
                'expiration_valid' => 'ok',
                'file_readable' => 'fail',
                'fit_to_play' => 'ok',
            ]],
            [$thiago, 'Autorización', 'pending', 'autorizacion_thiago_martinez.pdf', 'María Martínez (Madre)', '2026-08-12', null, [
                'identity_match' => 'ok',
                'tutor_signed' => 'warn',
                'expiration_valid' => 'ok',
                'file_readable' => 'ok',
                'fit_to_play' => 'ok',
            ]],
            [$benjamin, 'Uso de imagen', 'rejected', 'uso_imagen_benjamin_ruiz.pdf', 'Familiar de Ruiz (Padre)', '2026-08-08', 'Firma ilegible. Reenviar autorización de uso de imagen.', [
                'identity_match' => 'ok',
                'tutor_signed' => 'fail',
                'expiration_valid' => 'ok',
                'file_readable' => 'warn',
                'fit_to_play' => 'ok',
            ]],
            [$santiago, 'Apto médico', 'approved', 'apto_medico_santiago_gomez.pdf', 'Familiar de Gómez (Padre)', '2026-08-01', null, [
                'identity_match' => 'ok',
                'tutor_signed' => 'ok',
                'expiration_valid' => 'ok',
                'file_readable' => 'ok',
                'fit_to_play' => 'ok',
            ]],
        ];

        foreach ($figmaDocs as [$player, $type, $status, $originalName, $uploadedBy, $uploadedAt, $notes, $checklist]) {
            if (! $player) {
                continue;
            }

            PlayerDocument::updateOrCreate(
                ['player_id' => $player->id, 'type' => $type],
                [
                    'status' => $status,
                    'original_name' => $originalName,
                    'uploaded_by_name' => $uploadedBy,
                    'uploaded_at' => $uploadedAt,
                    'notes' => $notes,
                    'checklist' => $checklist,
                ]
            );
        }

        $planillaMatch = FixtureMatch::updateOrCreate(
            [
                'tournament_id' => $tournament->id,
                'home_team_id' => $teams['San Lorenzo']->id,
                'away_team_id' => $teams['Pampero']->id,
                'scheduled_at' => '2026-12-15 10:30:00',
            ],
            [
                'category_id' => $categories['Sub 12 Masculino']->id,
                'field_id' => $fieldBySimpleName['Cancha 3']->id,
                'stage' => 'Final Oro',
                'round' => 'Finales',
                'status' => 'validated',
                'home_score' => 3,
                'away_score' => 2,
                'duration_minutes' => 70,
                'referee_name' => 'Martín Sosa',
                'published' => true,
                'published_at' => now(),
            ]
        );

        $thiago = Player::query()->where('first_name', 'Thiago')->where('last_name', 'Martínez')->first();
        $mateo = Player::query()->where('first_name', 'Mateo')->where('last_name', 'Díaz')->first();
        $lucas = Player::query()->where('first_name', 'Lucas')->where('last_name', 'Pérez')->first();
        $santiago = Player::query()->where('first_name', 'Santiago')->where('last_name', 'Gómez')->first();

        $closedSheet = MatchSheet::updateOrCreate(
            ['match_id' => $planillaMatch->id],
            [
                'status' => 'closed',
                'validation_status' => 'validated',
                'current_step' => 4,
                'referee_name' => 'Martín Sosa',
                'assistant_name' => 'Carla Pérez',
                'responsible_name' => 'Árbitro Sosa',
                'home_score' => 3,
                'away_score' => 2,
                'incident_title' => 'Conducta inapropiada de un padre',
                'incident_team_id' => $teams['Pampero']->id,
                'incident_moment' => 'Segundo tiempo',
                'incident_notes' => 'Se dejó constancia para Fair Play y disciplina.',
                'locked' => true,
                'published' => true,
                'published_at' => now(),
            ]
        );

        $closedSheet->events()->delete();
        foreach ([
            [12, 'goal', $mateo, $teams['Pampero']->id, 'Gol de Pampero'],
            [18, 'goal', $thiago, $teams['San Lorenzo']->id, 'Gol de jugada'],
            [24, 'yellow', $lucas, $teams['Pampero']->id, 'Falta táctica'],
            [30, 'assist', $santiago, $teams['San Lorenzo']->id, 'Asistencia al segundo gol'],
            [31, 'goal', $thiago, $teams['San Lorenzo']->id, 'Gol de jugada, asistencia de Gómez'],
            [38, 'goal', $mateo, $teams['Pampero']->id, 'Empate parcial'],
            [52, 'goal', $thiago, $teams['San Lorenzo']->id, 'Gol del triunfo'],
        ] as [$minute, $type, $player, $teamId, $detail]) {
            MatchSheetEvent::create([
                'match_sheet_id' => $closedSheet->id,
                'type' => $type,
                'minute' => $minute,
                'player_id' => $player?->id,
                'team_id' => $teamId,
                'detail' => $detail,
            ]);
        }

        $closedSheet->incidents()->delete();
        MatchSheetIncident::insert([
            ['match_sheet_id' => $closedSheet->id, 'type' => 'Fair Play', 'title' => 'Conducta inapropiada de un padre', 'related_name' => 'Pampero', 'status' => 'applied', 'created_at' => now(), 'updated_at' => now()],
            ['match_sheet_id' => $closedSheet->id, 'type' => 'Tarjeta', 'title' => 'Amarilla', 'related_name' => 'Lucas Pérez', 'status' => 'confirmed', 'created_at' => now(), 'updated_at' => now()],
            ['match_sheet_id' => $closedSheet->id, 'type' => 'Informe', 'title' => 'Informe arbitral', 'related_name' => 'Árbitro', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $closedSheet->signatures()->delete();
        $closedSheet->signatures()->createMany([
            ['role' => 'referee', 'name' => 'Martín Sosa', 'signed' => true, 'signed_at' => now()],
            ['role' => 'away_delegate', 'name' => 'Carlos Pérez', 'signed' => false],
            ['role' => 'home_delegate', 'name' => 'Martín Sosa', 'signed' => true, 'signed_at' => now()],
        ]);

        $sheetSeeds = [
            ['Buen Ayre', 'Zelaya', 'loading', 'pending', 2, 'Asistente Ruiz', false],
            ['Leones FC', 'Unión FC', 'draft', 'pending', 1, 'Mesa 2', false],
            ['Atlético del Sur', 'Deportivo Norte', 'observed', 'review', 4, 'Árbitro Núñez', false],
        ];

        foreach ($sheetSeeds as [$home, $away, $status, $validation, $step, $responsible, $locked]) {
            $match = FixtureMatch::query()
                ->where('home_team_id', $teams[$home]->id)
                ->where('away_team_id', $teams[$away]->id)
                ->first();

            if (! $match) {
                continue;
            }

            $sheet = MatchSheet::updateOrCreate(
                ['match_id' => $match->id],
                [
                    'status' => $status,
                    'validation_status' => $validation,
                    'current_step' => $step,
                    'referee_name' => $match->referee_name,
                    'responsible_name' => $responsible,
                    'locked' => $locked,
                ]
            );
            $sheet->ensureSignatures();
        }

        $admin = User::where('email', 'admin@stctorneos.demo')->first();
        $valeria = User::where('email', 'torneo@stctorneos.demo')->first();
        $delegate = User::where('email', 'delegado@stctorneos.demo')->first();
        $sub12 = $categories['Sub 12 Masculino'];

        $news = ContentPost::updateOrCreate(
            ['slug' => 'fixture-final-oro-confirmado'],
            [
                'tournament_id' => $tournament->id,
                'category_id' => $sub12->id,
                'author_id' => $admin?->id,
                'type' => 'news',
                'title' => 'Fixture de Final Oro confirmado',
                'summary' => 'San Lorenzo vs Pampero se juega el 15 de diciembre a las 10:30 en Cancha 3.',
                'body' => 'La mesa central confirmó el cruce de Final Oro Sub 12. El partido queda publicado en la app con cancha, horario y planilla asociada.',
                'cover_path' => 'images/category-banner.svg',
                'audience' => 'public',
                'status' => 'published',
                'pinned' => true,
                'published_at' => now(),
            ]
        );

        ContentPost::updateOrCreate(
            ['slug' => 'cambio-horario-cancha-3'],
            [
                'tournament_id' => $tournament->id,
                'author_id' => $valeria?->id,
                'type' => 'announcement',
                'title' => 'Cambio de horario en Cancha 3',
                'summary' => 'El partido de la tarde se mueve 30 minutos por solape de campo.',
                'body' => 'Delegados de Unión FC y Pampero: el cruce de la tarde queda a las 14:00. Confirmar asistencia en la app.',
                'cover_path' => 'images/stc-logo.png',
                'audience' => 'delegates',
                'status' => 'scheduled',
                'scheduled_at' => '2026-12-14 18:00:00',
            ]
        );

        ContentPost::updateOrCreate(
            ['slug' => 'reglamento-fair-play'],
            [
                'tournament_id' => $tournament->id,
                'author_id' => $admin?->id,
                'type' => 'official',
                'title' => 'Reglamento de Fair Play',
                'summary' => 'Amarilla 1 punto, roja 3 e incidencia 2. Se aplica a todas las categorías.',
                'body' => 'El criterio de disciplina queda unificado para tablas y desempates. Las planillas observadas se revisan antes de publicar el resultado.',
                'cover_path' => 'images/category-banner.svg',
                'audience' => 'public',
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        ContentPost::updateOrCreate(
            ['slug' => 'placa-final-oro-sub-12'],
            [
                'tournament_id' => $tournament->id,
                'category_id' => $sub12->id,
                'author_id' => $valeria?->id,
                'type' => 'plaque',
                'title' => 'Placa Final Oro Sub 12',
                'summary' => 'San Lorenzo 3 - 2 Pampero',
                'body' => 'Resultado oficial publicado desde planilla cerrada.',
                'cover_path' => 'images/stc-logo.png',
                'audience' => 'public',
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        $sentNotice = AppNotification::updateOrCreate(
            ['title' => 'Resultado publicado: San Lorenzo 3-2 Pampero'],
            [
                'tournament_id' => $tournament->id,
                'content_post_id' => $news->id,
                'created_by' => $admin?->id,
                'channel' => 'both',
                'body' => 'Ya está el marcador oficial de la Final Oro Sub 12 en la app.',
                'audience' => 'all',
                'status' => 'sent',
                'sent_at' => now(),
            ]
        );

        $sentNotice->recipients()->delete();
        foreach (User::query()->where('status', 'active')->get() as $recipient) {
            AppNotificationRecipient::create([
                'app_notification_id' => $sentNotice->id,
                'user_id' => $recipient->id,
                'role_name' => $recipient->roleLabel(),
                'status' => 'sent',
            ]);
        }
        $sentNotice->update(['recipients_count' => $sentNotice->recipients()->count()]);

        AppNotification::updateOrCreate(
            ['title' => 'Recordatorio jornada 15/12'],
            [
                'tournament_id' => $tournament->id,
                'created_by' => $valeria?->id,
                'channel' => 'push',
                'body' => 'Mañana arranca la jornada. Revisá cancha y horario en el fixture.',
                'audience' => 'delegates',
                'status' => 'scheduled',
                'scheduled_at' => '2026-12-14 20:00:00',
            ]
        );

        AppNotification::updateOrCreate(
            ['title' => 'Aviso de lluvia — borrador'],
            [
                'tournament_id' => $tournament->id,
                'created_by' => $valeria?->id,
                'channel' => 'in_app',
                'body' => 'Si se confirma el frente de lluvia, Cancha 4 pasa a backup.',
                'audience' => 'staff',
                'status' => 'draft',
            ]
        );

        if ($delegate && isset($teams['Leones FC'])) {
            FavoriteTeam::updateOrCreate(
                ['user_id' => $delegate->id, 'team_id' => $teams['Leones FC']->id]
            );
        }
        if ($admin && isset($teams['San Lorenzo'])) {
            FavoriteTeam::updateOrCreate(
                ['user_id' => $admin->id, 'team_id' => $teams['San Lorenzo']->id]
            );
        }
        if ($valeria && isset($teams['Pampero'])) {
            FavoriteTeam::updateOrCreate(
                ['user_id' => $valeria->id, 'team_id' => $teams['Pampero']->id]
            );
        }

        FavoriteMatch::updateOrCreate(
            ['user_id' => $delegate?->id ?: $admin?->id, 'match_id' => $planillaMatch->id]
        );

        if (filter_var(env('SEED_DEMO_DATA', ! app()->environment('production')), FILTER_VALIDATE_BOOL)) {
            $this->call(DemoCompetitionSeeder::class);
            $this->call(DemoPresentationSeeder::class);
        }

        AuditLog::updateOrCreate(
            ['module' => 'Sistema', 'action' => 'seed_demo_data'],
            [
                'user_id' => User::where('email', 'admin@stctorneos.demo')->value('id'),
                'description' => 'Carga inicial de roles, permisos, usuarios, torneo activo, equipos y fixture demo.',
                'metadata' => ['source' => 'DatabaseSeeder', 'roles' => $roles->count(), 'users' => $users->count(), 'matches' => count($matches)],
                'created_at' => now(),
            ]
        );
    }
}
