<?php

namespace App\Support;

use App\Models\Category;
use App\Models\User;

class AdminNavigation
{
    /**
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    public static function items(?User $user): array
    {
        if (! $user || $user->restrictsToAssignedClub()) {
            return [];
        }

        return collect([
            ['Dashboard', route('dashboard'), 'Inicio', ['dashboard.view']],
            ['Torneos', route('admin.tournaments.index'), 'Copa y sedes', ['tournaments.manage']],
            ['Categorías', route('admin.categories.index'), 'Edades y formatos', ['tournaments.manage']],
            ['Clasificación', self::classificationHref($user), 'Tablas, cruces y fair play', ['matches.manage', 'match_sheets.manage']],
            ['Delegaciones', route('admin.delegations.index'), 'Clubes', ['delegations.manage']],
            ['Equipos', route('admin.teams.index'), 'Planteles', ['delegations.manage']],
            ['Jugadores', route('admin.players.index', ['status' => 'review']), 'Fichas, aprobación y habilitación', ['players.approve']],
            ['Revisión', route('admin.review.index'), 'Fichas y tutor', ['players.approve']],
            ['Campos', route('admin.fields.index'), 'Sedes', ['tournaments.manage']],
            ['Fixture', route('admin.fixture.index'), 'Partidos', ['matches.manage']],
            ['Planillas', route('admin.sheets.index'), 'Operación', ['match_sheets.manage']],
            ['Resultados', route('admin.results.index'), 'Tablas', ['matches.manage', 'match_sheets.manage']],
            ['Usuarios', route('admin.users.index'), 'Roles', ['users.manage']],
            ['Roles', route('admin.roles.index'), 'Permisos por rol', ['roles.manage']],
            ['Comunicaciones', route('admin.communications.index'), 'App y avisos', ['communications.manage']],
            ['Auditoría', route('admin.audit.index'), 'Control', ['audit.view']],
        ])->filter(fn (array $item) => $user->hasAnyPermission(...$item[3]))
            ->map(fn (array $item) => [$item[0], $item[1], $item[2]])
            ->values()
            ->all();
    }

    public static function searchAction(?string $active): string
    {
        $routeName = request()->route()?->getName();

        $stayOnPage = [
            'admin.tournaments.index',
            'admin.categories.index',
            'admin.categories.competition',
            'admin.categories.rankings',
            'admin.delegations.index',
            'admin.teams.index',
            'admin.players.index',
            'admin.documents.index',
            'admin.documents.requirements',
            'admin.fields.index',
            'admin.fixture.index',
            'admin.fixture.jornada',
            'admin.sheets.index',
            'admin.results.index',
            'admin.results.standings',
            'admin.results.brackets',
            'admin.results.fairplay',
            'admin.results.rankings',
            'admin.results.sanctions',
            'admin.results.rating',
            'admin.results.team',
            'admin.users.index',
            'admin.roles.index',
            'admin.roles.edit',
            'admin.review.index',
            'admin.communications.index',
            'admin.communications.notifications',
            'admin.communications.favorites',
            'admin.communications.plaques',
            'admin.audit.index',
            'admin.search',
        ];

        if (in_array($routeName, $stayOnPage, true)) {
            return url()->current();
        }

        return match ($active) {
            'Torneos' => route('admin.tournaments.index'),
            'Categorías' => route('admin.categories.index'),
            'Clasificación' => self::classificationHref(auth()->user()),
            'Delegaciones' => route('admin.delegations.index'),
            'Equipos' => route('admin.teams.index'),
            'Jugadores' => route('admin.players.index'),
            'Revisión' => route('admin.review.index'),
            'Documentación' => route('admin.players.index', ['status' => 'review']),
            'Campos' => route('admin.fields.index'),
            'Fixture' => route('admin.fixture.index'),
            'Planillas' => route('admin.sheets.index'),
            'Resultados' => route('admin.results.index'),
            'Usuarios' => route('admin.users.index'),
            'Roles' => route('admin.roles.index'),
            'Comunicaciones' => route('admin.communications.index'),
            'Auditoría' => route('admin.audit.index'),
            default => route('admin.search'),
        };
    }

    /**
     * @return list<string>
     */
    public static function searchPreserveKeys(?string $active): array
    {
        if (static::searchAction($active) === route('admin.search')) {
            return [];
        }

        return ['filter', 'status', 'team_id', 'category_id', 'tournament_id', 'type', 'modality', 'round', 'module', 'action', 'user_id', 'phase', 'date'];
    }

    public static function canCreate(?string $active, ?User $user): bool
    {
        if (! $user || in_array($active, ['Documentación', 'Resultados', 'Auditoría', 'Revisión', 'Clasificación', 'Roles'], true)) {
            return false;
        }

        return match ($active) {
            'Dashboard', null => $user->hasPermission('tournaments.manage') && $user->isSuperAdmin(),
            'Torneos' => $user->isSuperAdmin(),
            'Delegaciones', 'Equipos' => $user->hasPermission('delegations.manage'),
            'Jugadores' => $user->hasPermission('players.approve'),
            'Fixture' => $user->hasPermission('matches.manage'),
            'Planillas' => $user->hasPermission('match_sheets.manage'),
            'Usuarios' => $user->hasPermission('users.manage'),
            'Comunicaciones' => $user->hasPermission('communications.manage'),
            default => $user->hasPermission('tournaments.manage'),
        };
    }

    /**
     * @return list<array{role: string, name: string, email: string, sees: string}>
     */
    public static function demoAccounts(): array
    {
        return [
            ['Admin General', 'Admin General STC', 'admin@stctorneos.demo', 'Todos los módulos y todos los torneos'],
            ['Admin Torneo', 'Lucía Gómez', 'lucia@stctorneos.demo', 'Mismos módulos, sin Usuarios · solo su torneo'],
            ['Delegado', 'Sebastián Martínez', 'delegado@stctorneos.demo', 'Solo Operación y su club'],
            ['Asistente/árbitro', 'Diego Ruiz', 'coordinador@stctorneos.demo', 'Fixture, planillas, resultados y avisos'],
            ['Jugador', 'Lautaro Ruiz', 'jugador@stctorneos.demo', 'Torneo, categoría, fixture, rankings y mi ficha'],
            ['Tutor', 'María Ruiz', 'tutor@stctorneos.demo', 'Mis jugadores y fichas a completar'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function demoPasswords(): array
    {
        return [
            'tutor@stctorneos.demo' => TutorCredentials::defaultPassword(),
        ];
    }

    public static function demoPasswordFor(string $email): string
    {
        return self::demoPasswords()[strtolower(trim($email))] ?? DemoCredentials::password();
    }

    public static function classificationHref(?User $user): string
    {
        if (! $user) {
            return route('admin.results.standings');
        }

        $tournament = AdminContext::activeTournament($user);
        $category = AdminContext::activeCategory($user, $tournament)
            ?? Category::query()
                ->accessibleTo($user)
                ->when($tournament, fn ($query) => $query->where('tournament_id', $tournament->id))
                ->orderBy('name')
                ->first();

        return $category
            ? route('admin.categories.competition', $category)
            : route('admin.results.standings', AdminContext::tournamentQuery($tournament));
    }
}
