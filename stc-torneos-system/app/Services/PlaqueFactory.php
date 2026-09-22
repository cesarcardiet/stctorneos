<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ContentPost;
use App\Models\FixtureMatch;
use App\Models\Player;
use App\Models\RoundSelection;

class PlaqueFactory
{
    public function __construct(
        private readonly CompetitionBoard $board,
        private readonly StcRating $rating,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function kinds(): array
    {
        return [
            'match_day' => 'Match Day',
            'resultado' => 'Resultado',
            'figura' => 'Figura del partido',
            'equipo_fecha' => 'Equipo de la Fecha',
            'goleadores' => 'Goleadores',
            'tabla' => 'Tabla',
            'campeon' => 'Campeón',
            'player_card' => 'Player Card',
            'titular' => 'Titular',
            'podio' => 'Podio',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function generate(string $kind, array $context, int $authorId): ContentPost
    {
        [$title, $summary, $body, $tournamentId, $categoryId] = match ($kind) {
            'match_day' => $this->matchDay($context),
            'resultado' => $this->resultado($context),
            'figura' => $this->figura($context),
            'equipo_fecha' => $this->equipoFecha($context),
            'goleadores' => $this->goleadores($context),
            'tabla' => $this->tabla($context),
            'campeon' => $this->campeon($context),
            'player_card' => $this->playerCard($context),
            'titular' => $this->titular($context),
            'podio' => $this->podio($context),
            default => abort(422, 'Tipo de placa no reconocido.'),
        };

        return ContentPost::create([
            'tournament_id' => $tournamentId,
            'category_id' => $categoryId,
            'author_id' => $authorId,
            'type' => 'plaque',
            'title' => $title,
            'slug' => ContentPost::makeSlug($title),
            'summary' => $summary,
            'body' => $body,
            'audience' => 'public',
            'status' => 'published',
            'pinned' => false,
            'published_at' => now(),
            'notes' => 'kind='.$kind,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: string, 1: string, 2: string, 3: ?int, 4: ?int}
     */
    private function matchDay(array $context): array
    {
        $match = $this->match($context);

        return [
            'Match Day · '.$match->title(),
            ($match->scheduled_at?->format('d/m H:i') ?: 'Horario a confirmar').' · '.$match->field?->name,
            implode("\n", array_filter([
                $match->homeTeam?->name.' vs '.$match->awayTeam?->name,
                $match->category?->name,
                $match->field?->name,
                $match->scheduled_at?->format('d/m/Y H:i'),
            ])),
            $match->tournament_id,
            $match->category_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: string, 1: string, 2: string, 3: ?int, 4: ?int}
     */
    private function resultado(array $context): array
    {
        $match = $this->match($context);

        return [
            'Resultado · '.$match->title().' '.$match->scoreLine(),
            $match->scoreLine(),
            $match->homeTeam?->name.' '.$match->scoreLine().' '.$match->awayTeam?->name,
            $match->tournament_id,
            $match->category_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: string, 1: string, 2: string, 3: ?int, 4: ?int}
     */
    private function figura(array $context): array
    {
        $match = $this->match($context);
        $match->loadMissing('sheet.events.player');
        $motm = $match->sheet?->events->firstWhere('type', 'motm');
        $name = $motm?->player?->fullName() ?: 'Figura a confirmar';

        return [
            'Figura · '.$name,
            $match->title(),
            $name.' · '.$match->title(),
            $match->tournament_id,
            $match->category_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: string, 1: string, 2: string, 3: ?int, 4: ?int}
     */
    private function equipoFecha(array $context): array
    {
        $category = $this->category($context);
        $round = (string) ($context['round'] ?? 'Fecha 1');
        $names = RoundSelection::query()
            ->with('player')
            ->where('category_id', $category->id)
            ->where('round', $round)
            ->where('selected', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (RoundSelection $row) => $row->player?->fullName())
            ->filter()
            ->implode(', ');

        if ($names === '') {
            $names = $this->rating->teamOfRound($category, $round)
                ->map(fn (array $row) => $row['player']->fullName())
                ->implode(', ');
        }

        return [
            'Equipo de la Fecha · '.$category->name.' · '.$round,
            $round,
            $names ?: 'Todavía no hay once confirmado.',
            $category->tournament_id,
            $category->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: string, 1: string, 2: string, 3: ?int, 4: ?int}
     */
    private function goleadores(array $context): array
    {
        $category = $this->category($context);
        $lines = $this->board->rankingList($category, 'goal', 8)
            ->map(fn (array $row) => $row[0].' ('.$row[1].') · '.$row[2])
            ->implode("\n");

        return [
            'Goleadores · '.$category->name,
            'Tabla de goleadores',
            $lines ?: 'Sin goles cargados.',
            $category->tournament_id,
            $category->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: string, 1: string, 2: string, 3: ?int, 4: ?int}
     */
    private function tabla(array $context): array
    {
        $category = $this->category($context);
        $lines = $this->board->standingsByGroup($category)
            ->flatten(1)
            ->take(8)
            ->map(fn (array $row) => $row['position'].'. '.$row['team']->name.' · '.$row['points'].' pts')
            ->implode("\n");

        return [
            'Tabla · '.$category->name,
            'Posiciones',
            $lines ?: 'Sin partidos cerrados.',
            $category->tournament_id,
            $category->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: string, 1: string, 2: string, 3: ?int, 4: ?int}
     */
    private function campeon(array $context): array
    {
        $category = $this->category($context);
        $leader = $this->board->standingsByGroup($category)->flatten(1)->first();
        $name = $leader['team']->name ?? 'Campeón a confirmar';

        return [
            'Campeón · '.$name,
            $category->name,
            $name.' · '.$category->name,
            $category->tournament_id,
            $category->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: string, 1: string, 2: string, 3: ?int, 4: ?int}
     */
    private function playerCard(array $context): array
    {
        $player = Player::query()->with(['team.category', 'team.tournament'])->findOrFail((int) ($context['player_id'] ?? 0));

        return [
            'Player Card · '.$player->fullName(),
            ($player->team?->name ?? 'Sin equipo').' · N° '.($player->jersey_number ?: '—'),
            $player->fullName()."\n".$player->position."\n".$player->team?->name,
            $player->team?->tournament_id,
            $player->team?->category_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: string, 1: string, 2: string, 3: ?int, 4: ?int}
     */
    private function titular(array $context): array
    {
        $match = $this->match($context);
        $side = ($context['side'] ?? 'home') === 'away' ? $match->awayTeam : $match->homeTeam;
        $names = $side?->players?->take(11)->map(fn (Player $player) => $player->fullName())->implode(', ');

        return [
            'Titular · '.$side?->name,
            $match->title(),
            $names ?: 'Plantel sin jugadores cargados.',
            $match->tournament_id,
            $match->category_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: string, 1: string, 2: string, 3: ?int, 4: ?int}
     */
    private function podio(array $context): array
    {
        $category = $this->category($context);
        $top = $this->board->standingsByGroup($category)->flatten(1)->take(3);
        $lines = $top->map(fn (array $row, int $index) => ($index + 1).'º '.$row['team']->name)->implode("\n");

        return [
            'Podio · '.$category->name,
            'Top 3',
            $lines ?: 'Todavía no hay podio.',
            $category->tournament_id,
            $category->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function match(array $context): FixtureMatch
    {
        return FixtureMatch::query()
            ->with(['homeTeam.players', 'awayTeam.players', 'category', 'field', 'sheet.events.player'])
            ->findOrFail((int) ($context['match_id'] ?? 0));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function category(array $context): Category
    {
        if (! empty($context['category_id'])) {
            return Category::query()->with('tournament')->findOrFail((int) $context['category_id']);
        }

        return $this->match($context)->category()->with('tournament')->firstOrFail();
    }
}
