<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\ContentPost;
use App\Models\Delegation;
use App\Models\FavoriteMatch;
use App\Models\FavoriteTeam;
use App\Models\Field;
use App\Models\FixtureMatch;
use App\Models\MatchSheet;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AdminSearch
{
    /**
     * @return list<array{label: string, href: string, items: list<array{title: string, meta: string, href: string}>}>
     */
    public function search(?User $user, string $term, int $limit = 6): array
    {
        $term = trim($term);

        if (! $user || $term === '') {
            return [];
        }

        $like = '%'.$term.'%';
        $groups = [];

        if ($user->hasPermission('tournaments.manage')) {
            $groups[] = $this->group(
                'Torneos',
                route('admin.tournaments.index', ['search' => $term]),
                Tournament::query()
                    ->when(
                        ! $user->canAccessAllTournaments(),
                        fn (Builder $query) => $query->whereIn('id', $user->assignedTournamentIds() ?: [0])
                    )
                    ->where(fn (Builder $query) => $query
                        ->where('name', 'like', $like)
                        ->orWhere('edition', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhere('country', 'like', $like)
                        ->orWhere('location', 'like', $like)
                        ->orWhere('venue_name', 'like', $like))
                    ->orderBy('name')
                    ->limit($limit)
                    ->get()
                    ->map(fn (Tournament $item) => [
                        'title' => $item->name,
                        'meta' => trim($item->edition.' · '.$item->city),
                        'href' => route('admin.tournaments.show', $item),
                    ])
                    ->all()
            );

            $groups[] = $this->group(
                'Categorías',
                route('admin.categories.index', ['search' => $term]),
                Category::query()
                    ->accessibleTo($user)
                    ->with('tournament')
                    ->where(fn (Builder $query) => $query
                        ->where('name', 'like', $like)
                        ->orWhere('birth_year', 'like', $like)
                        ->orWhere('branch', 'like', $like)
                        ->orWhere('modality', 'like', $like)
                        ->orWhere('custom_modality', 'like', $like)
                        ->orWhere('competition_format', 'like', $like))
                    ->orderBy('name')
                    ->limit($limit)
                    ->get()
                    ->map(fn (Category $item) => [
                        'title' => $item->name,
                        'meta' => trim(($item->tournament?->name ?? '').' · '.$item->birth_year),
                        'href' => route('admin.categories.show', $item),
                    ])
                    ->all()
            );

            $groups[] = $this->group(
                'Campos',
                route('admin.fields.index', ['search' => $term]),
                Field::query()
                    ->accessibleTo($user)
                    ->with('venue')
                    ->where(fn (Builder $query) => $query
                        ->where('name', 'like', $like)
                        ->orWhere('surface', 'like', $like)
                        ->orWhereHas('venue', fn (Builder $query) => $query
                            ->where('name', 'like', $like)
                            ->orWhere('city', 'like', $like)))
                    ->orderBy('name')
                    ->limit($limit)
                    ->get()
                    ->map(fn (Field $item) => [
                        'title' => $item->name,
                        'meta' => $item->venue?->name ?? 'Sede',
                        'href' => route('admin.fields.show', $item),
                    ])
                    ->all()
            );
        }

        if ($user->hasPermission('delegations.manage')) {
            $groups[] = $this->group(
                'Delegaciones',
                route('admin.delegations.index', ['search' => $term]),
                Delegation::query()
                    ->accessibleTo($user)
                    ->where(fn (Builder $query) => $query
                        ->where('name', 'like', $like)
                        ->orWhere('delegate_name', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhere('country', 'like', $like))
                    ->orderBy('name')
                    ->limit($limit)
                    ->get()
                    ->map(fn (Delegation $item) => [
                        'title' => $item->name,
                        'meta' => trim($item->city.' · '.$item->country),
                        'href' => route('admin.delegations.show', $item),
                    ])
                    ->all()
            );

            $groups[] = $this->group(
                'Equipos',
                route('admin.teams.index', ['search' => $term]),
                Team::query()
                    ->accessibleTo($user)
                    ->with(['category', 'delegation'])
                    ->where(fn (Builder $query) => $query
                        ->where('name', 'like', $like)
                        ->orWhere('delegation_name', 'like', $like)
                        ->orWhere('group_name', 'like', $like))
                    ->orderBy('name')
                    ->limit($limit)
                    ->get()
                    ->map(fn (Team $item) => [
                        'title' => $item->name,
                        'meta' => trim(($item->category?->name ?? '').' · '.($item->delegation?->name ?? $item->delegation_name)),
                        'href' => route('admin.teams.show', $item),
                    ])
                    ->all()
            );
        }

        if ($user->hasPermission('players.approve')) {
            $groups[] = $this->group(
                'Jugadores',
                route('admin.players.index', ['search' => $term]),
                Player::query()
                    ->when(
                        ! $user->canAccessAllTournaments(),
                        fn (Builder $query) => $query->whereHas('team', fn (Builder $team) => $team->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0]))
                    )
                    ->with('team')
                    ->where(fn (Builder $query) => $query
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('document_number', 'like', $like)
                        ->orWhereHas('team', fn (Builder $query) => $query->where('name', 'like', $like)))
                    ->orderBy('last_name')
                    ->limit($limit)
                    ->get()
                    ->map(fn (Player $item) => [
                        'title' => $item->fullName(),
                        'meta' => trim(($item->team?->name ?? 'Sin equipo').' · '.$item->formattedDocument()),
                        'href' => route('admin.players.show', $item),
                    ])
                    ->all()
            );

            $groups[] = $this->group(
                'Documentación',
                route('admin.documents.index', ['search' => $term]),
                PlayerDocument::query()
                    ->when(
                        ! $user->canAccessAllTournaments(),
                        fn (Builder $query) => $query->whereHas('player.team', fn (Builder $team) => $team->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0]))
                    )
                    ->with('player.team')
                    ->where(fn (Builder $query) => $query
                        ->where('type', 'like', $like)
                        ->orWhereHas('player', fn (Builder $query) => $query
                            ->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('document_number', 'like', $like)))
                    ->latest()
                    ->limit($limit)
                    ->get()
                    ->map(fn (PlayerDocument $item) => [
                        'title' => ($item->player?->fullName() ?? 'Jugador').' · '.$item->type,
                        'meta' => $item->statusLabel(),
                        'href' => route('admin.documents.show', $item),
                    ])
                    ->all()
            );
        }

        if ($user->hasPermission('matches.manage')) {
            $groups[] = $this->group(
                'Fixture',
                route('admin.fixture.index', ['search' => $term]),
                $this->matchesQuery($user, $like)
                    ->limit($limit)
                    ->get()
                    ->map(fn (FixtureMatch $item) => [
                        'title' => $item->title(),
                        'meta' => trim(($item->category?->name ?? '').' · '.$item->statusLabel()),
                        'href' => route('admin.fixture.show', $item),
                    ])
                    ->all()
            );
        }

        if ($user->hasPermission('match_sheets.manage')) {
            $groups[] = $this->group(
                'Planillas',
                route('admin.sheets.index', ['search' => $term]),
                MatchSheet::query()
                    ->accessibleTo($user)
                    ->with(['match.homeTeam', 'match.awayTeam'])
                    ->where(fn (Builder $query) => $query
                        ->where('referee_name', 'like', $like)
                        ->orWhere('assistant_name', 'like', $like)
                        ->orWhere('responsible_name', 'like', $like)
                        ->orWhere('status', 'like', $like)
                        ->orWhereHas('match.homeTeam', fn (Builder $query) => $query->where('name', 'like', $like))
                        ->orWhereHas('match.awayTeam', fn (Builder $query) => $query->where('name', 'like', $like)))
                    ->latest()
                    ->limit($limit)
                    ->get()
                    ->map(fn (MatchSheet $item) => [
                        'title' => $item->match?->title() ?? 'Planilla',
                        'meta' => $item->statusLabel(),
                        'href' => route('admin.sheets.datos', $item),
                    ])
                    ->all()
            );
        }

        if ($user->hasAnyPermission('matches.manage', 'match_sheets.manage')) {
            $groups[] = $this->group(
                'Resultados',
                route('admin.results.index', ['search' => $term]),
                $this->matchesQuery($user, $like)
                    ->whereIn('status', ['live', 'finished', 'validated'])
                    ->limit($limit)
                    ->get()
                    ->map(fn (FixtureMatch $item) => [
                        'title' => $item->title().' '.$item->scoreLine(),
                        'meta' => $item->statusLabel(),
                        'href' => route('admin.results.index', ['search' => $term]),
                    ])
                    ->all()
            );
        }

        if ($user->hasPermission('users.manage')) {
            $groups[] = $this->group(
                'Usuarios',
                route('admin.users.index', ['search' => $term]),
                User::query()
                    ->where(fn (Builder $query) => $query
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('current_scope', 'like', $like))
                    ->orderBy('name')
                    ->limit($limit)
                    ->get()
                    ->map(fn (User $item) => [
                        'title' => $item->name,
                        'meta' => $item->email,
                        'href' => route('admin.users.show', $item),
                    ])
                    ->all()
            );
        }

        if ($user->hasPermission('communications.manage')) {
            $groups[] = $this->group(
                'Comunicaciones',
                route('admin.communications.index', ['search' => $term]),
                ContentPost::query()
                    ->accessibleTo($user)
                    ->where(fn (Builder $query) => $query
                        ->where('title', 'like', $like)
                        ->orWhere('summary', 'like', $like)
                        ->orWhere('type', 'like', $like))
                    ->latest()
                    ->limit($limit)
                    ->get()
                    ->map(fn (ContentPost $item) => [
                        'title' => $item->title,
                        'meta' => $item->typeLabel().' · '.$item->statusLabel(),
                        'href' => route('admin.communications.show', $item),
                    ])
                    ->all()
            );

            $groups[] = $this->group(
                'Notificaciones',
                route('admin.communications.notifications', ['search' => $term]),
                AppNotification::query()
                    ->accessibleTo($user)
                    ->where(fn (Builder $query) => $query
                        ->where('title', 'like', $like)
                        ->orWhere('body', 'like', $like)
                        ->orWhere('audience', 'like', $like))
                    ->latest()
                    ->limit($limit)
                    ->get()
                    ->map(fn (AppNotification $item) => [
                        'title' => $item->title,
                        'meta' => $item->statusLabel(),
                        'href' => route('admin.communications.notifications.show', $item),
                    ])
                    ->all()
            );

            $favoriteTeams = FavoriteTeam::query()
                ->with(['user', 'team'])
                ->when(
                    ! $user->canAccessAllTournaments(),
                    fn (Builder $query) => $query->whereHas('team', fn (Builder $team) => $team->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0]))
                )
                ->where(fn (Builder $query) => $query
                    ->whereHas('team', fn (Builder $query) => $query->where('name', 'like', $like))
                    ->orWhereHas('user', fn (Builder $query) => $query->where('name', 'like', $like)))
                ->latest()
                ->limit($limit)
                ->get()
                ->map(fn (FavoriteTeam $item) => [
                    'title' => $item->team?->name ?? 'Equipo',
                    'meta' => 'Favorito de '.($item->user?->name ?? 'usuario'),
                    'href' => route('admin.communications.favorites', ['search' => $term]),
                ]);

            $favoriteMatches = FavoriteMatch::query()
                ->with(['user', 'match.homeTeam', 'match.awayTeam'])
                ->when(
                    ! $user->canAccessAllTournaments(),
                    fn (Builder $query) => $query->whereHas('match', fn (Builder $match) => $match->whereIn('tournament_id', $user->assignedTournamentIds() ?: [0]))
                )
                ->where(fn (Builder $query) => $query
                    ->whereHas('user', fn (Builder $query) => $query->where('name', 'like', $like))
                    ->orWhereHas('match.homeTeam', fn (Builder $query) => $query->where('name', 'like', $like))
                    ->orWhereHas('match.awayTeam', fn (Builder $query) => $query->where('name', 'like', $like)))
                ->latest()
                ->limit($limit)
                ->get()
                ->map(fn (FavoriteMatch $item) => [
                    'title' => $item->match?->title() ?? 'Partido',
                    'meta' => 'Favorito de '.($item->user?->name ?? 'usuario'),
                    'href' => route('admin.communications.favorites', ['search' => $term]),
                ]);

            $groups[] = $this->group(
                'Favoritos',
                route('admin.communications.favorites', ['search' => $term]),
                $favoriteTeams->concat($favoriteMatches)->take($limit)->values()->all()
            );
        }

        if ($user->hasPermission('audit.view')) {
            $groups[] = $this->group(
                'Auditoría',
                route('admin.audit.index', ['search' => $term]),
                AuditLog::query()
                    ->when(
                        ! $user->canAccessAllTournaments() && $user->assignedTournamentIds(),
                        function (Builder $query) use ($user) {
                            $ids = $user->assignedTournamentIds();
                            $query->where(function (Builder $query) use ($ids) {
                                $query
                                    ->whereHas('user', fn (Builder $userQuery) => $userQuery->whereIn('tournament_id', $ids)->orWhereIn('extra_tournament_id', $ids))
                                    ->orWhereNull('user_id');
                            });
                        }
                    )
                    ->where(fn (Builder $query) => $query
                        ->where('description', 'like', $like)
                        ->orWhere('action', 'like', $like)
                        ->orWhere('module', 'like', $like))
                    ->latest('id')
                    ->limit($limit)
                    ->get()
                    ->map(fn (AuditLog $item) => [
                        'title' => $item->description ?: ($item->module.' · '.$item->action),
                        'meta' => trim($item->module.' · '.$item->created_at?->format('d/m H:i')),
                        'href' => route('admin.audit.show', $item),
                    ])
                    ->all()
            );
        }

        return array_values(array_filter($groups, fn (array $group) => $group['items'] !== []));
    }

    /**
     * @return list<array{title: string, meta: string, href: string, group: string}>
     */
    public function suggestions(?User $user, string $term, ?string $scope = null, int $limit = 8): array
    {
        $term = trim($term);
        $tokens = preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (! $user || $tokens === []) {
            return [];
        }

        $items = [];

        foreach ($this->search($user, $tokens[0], 8) as $group) {
            foreach ($group['items'] as $item) {
                $haystack = $this->normalize($item['title'].' '.$item['meta'].' '.$group['label']);
                $matchesAll = true;

                foreach ($tokens as $token) {
                    if (! str_contains($haystack, $this->normalize($token))) {
                        $matchesAll = false;
                        break;
                    }
                }

                if (! $matchesAll) {
                    continue;
                }

                $items[] = [
                    'title' => $item['title'],
                    'meta' => $item['meta'],
                    'href' => $item['href'],
                    'group' => $group['label'],
                    'score' => $this->score($term, $item['title'], $item['meta'], $group['label'] === $scope),
                ];
            }
        }

        usort($items, fn (array $left, array $right) => $right['score'] <=> $left['score']);

        return array_map(
            fn (array $item) => [
                'title' => $item['title'],
                'meta' => $item['meta'],
                'href' => $item['href'],
                'group' => $item['group'],
            ],
            array_slice($items, 0, $limit)
        );
    }

    /**
     * @param  list<array{title: string, meta: string, href: string}>  $items
     * @return array{label: string, href: string, items: list<array{title: string, meta: string, href: string}>}
     */
    private function group(string $label, string $href, array $items): array
    {
        return compact('label', 'href', 'items');
    }

    private function score(string $term, string $title, string $meta, bool $scoped): int
    {
        $needle = $this->normalize($term);
        $titleFold = $this->normalize($title);
        $metaFold = $this->normalize($meta);
        $score = $scoped ? 40 : 0;

        if ($titleFold === $needle) {
            $score += 120;
        } elseif (str_starts_with($titleFold, $needle)) {
            $score += 90;
        } elseif (str_contains($titleFold, $needle)) {
            $score += 55;
        }

        foreach (preg_split('/\s+/u', $titleFold, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (str_starts_with($word, $needle)) {
                $score += 28;
            }
        }

        if (str_contains($metaFold, $needle)) {
            $score += 12;
        }

        $parts = preg_split('/\s+/u', $needle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) > 1) {
            $blob = $titleFold.' '.$metaFold;
            $matched = count(array_filter($parts, fn (string $part) => str_contains($blob, $part)));
            $score += $matched * 18;
        }

        return $score;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return str_replace(
            ['á', 'à', 'ä', 'é', 'è', 'ë', 'í', 'ì', 'ï', 'ó', 'ò', 'ö', 'ú', 'ù', 'ü', 'ñ', 'ç'],
            ['a', 'a', 'a', 'e', 'e', 'e', 'i', 'i', 'i', 'o', 'o', 'o', 'u', 'u', 'u', 'n', 'c'],
            $value
        );
    }

    private function matchesQuery(User $user, string $like): Builder
    {
        return FixtureMatch::query()
            ->accessibleTo($user)
            ->with(['homeTeam', 'awayTeam', 'category', 'field'])
            ->where(fn (Builder $query) => $query
                ->where('stage', 'like', $like)
                ->orWhere('round', 'like', $like)
                ->orWhere('referee_name', 'like', $like)
                ->orWhereHas('homeTeam', fn (Builder $query) => $query->where('name', 'like', $like))
                ->orWhereHas('awayTeam', fn (Builder $query) => $query->where('name', 'like', $like))
                ->orWhereHas('category', fn (Builder $query) => $query->where('name', 'like', $like))
                ->orWhereHas('field', fn (Builder $query) => $query->where('name', 'like', $like)))
            ->orderByDesc('scheduled_at');
    }
}
