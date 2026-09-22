<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Team;
use App\Models\User;

/**
 * Matriz de acceso a Operación (/operacion) por rol.
 * Fuente única para documentación, pantalla "Mi cuenta" y tests.
 */
class WorkspaceAccess
{
    public function __construct(private ?User $user) {}

    public static function for(?User $user): self
    {
        return new self($user);
    }

    public function roleSlug(): ?string
    {
        return $this->user?->primaryRole()?->slug;
    }

    public function isGuest(): bool
    {
        return ! $this->user;
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->user?->isSuperAdmin();
    }

    public function isAdminTorneo(): bool
    {
        return (bool) $this->user?->hasRole('admin-torneo');
    }

    public function isDelegado(): bool
    {
        return (bool) $this->user?->restrictsToAssignedClub();
    }

    public function isMatchStaff(): bool
    {
        return (bool) $this->user?->isMatchStaffOnly();
    }

    public function isMesa(): bool
    {
        return (bool) $this->user?->hasRole('asistente-mesa');
    }

    public function isJugador(): bool
    {
        return (bool) $this->user?->isPlayerAccount();
    }

    public function isTutor(): bool
    {
        return (bool) $this->user?->isTutorAccount();
    }

    public function canAccessOperacion(): bool
    {
        return (bool) $this->user?->hasPermission('dashboard.view');
    }

    public function canAccessAdminWeb(): bool
    {
        if (! $this->user) {
            return false;
        }

        return ! $this->user->restrictsToAssignedClub()
            && ! $this->user->isPlayerAccount()
            && ! $this->user->isTutorAccount();
    }

    public function canBrowseAllTournaments(): bool
    {
        return (bool) $this->user?->canBrowseAllTournaments();
    }

    public function canCreateTournament(): bool
    {
        return (bool) $this->user?->isSuperAdmin();
    }

    public function canDeleteTournament(): bool
    {
        return (bool) $this->user?->isSuperAdmin();
    }

    public function canManageCategory(Category $category): bool
    {
        if (! $this->user?->canAccessTournament((int) $category->tournament_id)) {
            return false;
        }

        return $this->canEditCategoryStructure();
    }

    public function canEditCategoryStructure(): bool
    {
        if (! $this->user || $this->isDelegado() || $this->isMatchStaff() || $this->isJugador() || $this->isTutor()) {
            return false;
        }

        return $this->user->hasAnyPermission(
            'tournaments.manage',
            'delegations.manage',
            'players.approve',
            'matches.manage',
        );
    }

    public function canViewCategoryCompetition(Category $category): bool
    {
        return (bool) $this->user?->canAccessTournament((int) $category->tournament_id);
    }

    public function canManageTeamRoster(Team $team): bool
    {
        if (! $this->user || $this->isMatchStaff() || $this->isJugador() || $this->isTutor()) {
            return false;
        }

        if ($this->isDelegado()) {
            $ownsTeam = Team::query()->accessibleTo($this->user)->whereKey($team->id)->exists();
            if (! $ownsTeam) {
                return false;
            }
            $team->loadMissing('category');

            return (bool) $team->category?->acceptsRosterEdits($team);
        }

        return $this->user->hasAnyPermission('delegations.manage', 'players.approve', 'tournaments.manage');
    }

    public function canShareRosterLink(Team $team): bool
    {
        return $this->canManageTeamRoster($team);
    }

    public function canOperateMatches(): bool
    {
        if (! $this->user || $this->isDelegado() || $this->isJugador() || $this->isTutor()) {
            return false;
        }

        return $this->user->hasAnyPermission('matches.manage', 'match_sheets.manage');
    }

    public function canScheduleMatches(): bool
    {
        return (bool) $this->user?->hasPermission('tournaments.manage');
    }

    public function canManageClubs(): bool
    {
        if (! $this->user || $this->isDelegado() || $this->isJugador() || $this->isTutor()) {
            return false;
        }

        return $this->user->hasAnyPermission('tournaments.manage', 'users.manage', 'delegations.manage');
    }

    public function canManageUsers(): bool
    {
        return (bool) $this->user?->hasPermission('users.manage');
    }

    public function canViewRankings(): bool
    {
        return $this->canEditCategoryStructure() || $this->isJugador() || $this->isTutor();
    }

    public function canViewMedia(): bool
    {
        return $this->canEditCategoryStructure() || $this->isTutor();
    }

    public function canViewSettings(): bool
    {
        return $this->canEditCategoryStructure();
    }

    public function canViewPlanillas(): bool
    {
        return $this->canOperateMatches() || $this->isMesa();
    }

    public function canViewInscriptions(Category $category): bool
    {
        if (! $this->user?->canAccessTournament((int) $category->tournament_id)) {
            return false;
        }

        if ($this->canReviewInscriptions()) {
            return true;
        }

        if ($this->isDelegado()) {
            return Team::query()->accessibleTo($this->user)->where('category_id', $category->id)->exists();
        }

        return false;
    }

    public function canReviewInscriptions(): bool
    {
        return $this->canEditCategoryStructure()
            && (bool) $this->user?->hasAnyPermission('players.approve', 'tournaments.manage', 'delegations.manage');
    }

    /**
     * Resumen legible para la pantalla Mi cuenta.
     *
     * @return list<string>
     */
    public function capabilityLines(): array
    {
        if (! $this->user) {
            return [];
        }

        return match ($this->roleSlug()) {
            'super-admin' => [
                'Acceso total a Operación y Admin Web.',
                'Creá y eliminá torneos, categorías, clubes y usuarios.',
                'Generá enlaces de plantel, configurá categorías y planillas.',
            ],
            'admin-torneo' => [
                'Gestioná tu(s) torneo(s) asignado(s) en Operación.',
                'Configuración de categorías, fixture, inscripciones y documentación.',
                'Generá enlaces de plantel para cualquier equipo de tu torneo.',
                'Admin Web: todos los módulos excepto Usuarios globales.',
            ],
            'delegado' => [
                'Ves tu club y tus equipos en las categorías del torneo.',
                'Cargá y editá jugadores de tu plantel (si inscripciones abiertas).',
                'Generá el enlace para que otros carguen jugadores sin login.',
                'No entrás al Admin Web ni a Configuración de categoría.',
            ],
            'asistente-arbitro' => [
                'Ves todos los torneos en Operación.',
                'Clasificación, fixture, equipos y jugadores (solo lectura).',
                'Cargá resultados y planillas de partido.',
                'No generás enlaces de plantel ni configurás categorías.',
            ],
            'asistente-mesa' => [
                'Operación de planillas de partido.',
                'Sin acceso a configuración ni planteles.',
            ],
            'jugador' => [
                'Mi ficha, datos personales, documentos y credencial STC.',
                'Descargá tu documentación y la ficha completa en PDF (imprimir).',
                'Consultá clasificación, fixture, partidos y rankings (solo lectura).',
                'En cada partido: horario, equipos, goles, tarjetas e historial de jugadas.',
            ],
            'tutor' => [
                'Ingresá con tu correo y la clave inicial del tutor.',
                'Completá fichas de tus hijos; si cargás el email del jugador, habilitás su portal.',
                'Clave tutor: '.TutorCredentials::defaultPassword().' · jugador: '.PlayerCredentials::defaultPassword().'.',
                'Consultá todos los torneos en solo lectura (sin Configuración).',
            ],
            default => [
                'Rol: '.$this->user->roleLabel(),
            ],
        };
    }

    /**
     * Matriz estática documentada (pantalla × rol).
     *
     * @return array<string, array<string, string>>
     */
    public static function documentedMatrix(): array
    {
        $v = 'Ver';
        $e = 'Editar';
        $x = '—';

        return [
            'Puerta torneos' => [
                'super-admin' => $e,
                'admin-torneo' => $v.' (alcance)',
                'delegado' => $v.' (mi club)',
                'asistente-arbitro' => $v.' (todos)',
                'asistente-mesa' => $v,
                'jugador' => $x,
                'tutor' => $v.' (todos)',
            ],
            'Inicio categoría' => [
                'super-admin' => $e,
                'admin-torneo' => $e,
                'delegado' => $v,
                'asistente-arbitro' => $v,
                'asistente-mesa' => $v,
                'jugador' => $x,
                'tutor' => $v,
            ],
            'Clasificación / fixture' => [
                'super-admin' => $e,
                'admin-torneo' => $e,
                'delegado' => $v,
                'asistente-arbitro' => $e.' resultados',
                'asistente-mesa' => $v,
                'jugador' => $v,
                'tutor' => $v,
            ],
            'Equipos / jugadores' => [
                'super-admin' => $e,
                'admin-torneo' => $e,
                'delegado' => $e.' su club',
                'asistente-arbitro' => $v,
                'asistente-mesa' => $x,
                'jugador' => $x,
                'tutor' => $v,
            ],
            'Enlace plantel (M01)' => [
                'super-admin' => $e,
                'admin-torneo' => $e,
                'delegado' => $e.' su club',
                'asistente-arbitro' => $v.' copiar si existe',
                'asistente-mesa' => $x,
                'jugador' => $x,
                'tutor' => $x,
            ],
            'Planillas partido' => [
                'super-admin' => $e,
                'admin-torneo' => $e,
                'delegado' => $x,
                'asistente-arbitro' => $e,
                'asistente-mesa' => $e,
                'jugador' => $x,
                'tutor' => $x,
            ],
            'Configuración categoría' => [
                'super-admin' => $e,
                'admin-torneo' => $e,
                'delegado' => $x,
                'asistente-arbitro' => $x,
                'asistente-mesa' => $x,
                'jugador' => $x,
                'tutor' => $x,
            ],
            'Rankings / media' => [
                'super-admin' => $e,
                'admin-torneo' => $e,
                'delegado' => $x,
                'asistente-arbitro' => $x,
                'asistente-mesa' => $x,
                'jugador' => $v,
                'tutor' => $v,
            ],
            'Mi ficha / credencial' => [
                'super-admin' => $x,
                'admin-torneo' => $x,
                'delegado' => $x,
                'asistente-arbitro' => $x,
                'asistente-mesa' => $x,
                'jugador' => $e,
                'tutor' => $e.' mis jugadores',
            ],
            'Admin Web (/admin)' => [
                'super-admin' => $e,
                'admin-torneo' => $e.' sin Usuarios',
                'delegado' => $x,
                'asistente-arbitro' => $e.' planillas',
                'asistente-mesa' => $e.' planillas',
                'jugador' => $x,
                'tutor' => $x,
            ],
        ];
    }
}
