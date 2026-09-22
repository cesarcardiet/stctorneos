<?php



namespace App\Http\Controllers\Workspace;



use App\Http\Controllers\Controller;

use App\Http\Controllers\Workspace\Concerns\ResolvesWorkspace;

use App\Models\Category;

use App\Models\FixtureMatch;

use App\Models\MatchSheetEvent;

use App\Models\Player;

use App\Models\Team;

use App\Services\CompetitionBoard;

use App\Support\CategoryWorkspace;

use App\Support\ReportPdf;

use Illuminate\Http\Request;

use Illuminate\Http\Response;

use Illuminate\Support\Collection;

use Illuminate\View\View;



class CategoryReportController extends Controller

{

    use ResolvesWorkspace;



    public function teams(Request $request, Category $category, CompetitionBoard $board): View|Response

    {

        $this->assertReportsAccess($request, $category);

        $category->load(['tournament', 'teams.delegation']);



        $workspace = $category->workspace();

        $allColumns = CategoryWorkspace::columnLabels();

        $requested = collect($request->input('columns', $workspace['visible_columns'] ?? []))

            ->map(fn ($column) => (string) $column)

            ->filter(fn ($column) => isset($allColumns[$column]))

            ->values()

            ->all();



        if ($requested === []) {

            $requested = ['pts', 'j', 'g', 'e', 'p'];

        }



        $groups = $board->standingsByGroup($category);

        $shortLabels = CategoryWorkspace::columnShortLabels();



        return ReportPdf::render($request, 'workspace.reports.teams', [

            'category' => $category,

            'tournament' => $category->tournament,

            'groups' => $groups,

            'columns' => $requested,

            'columnLabels' => $allColumns,

            'shortLabels' => $shortLabels,

        ], 'equipos-cat-'.$category->id, 'portrait');

    }



    public function players(Request $request, Category $category): View|Response

    {

        $this->assertReportsAccess($request, $category);

        $category->load(['tournament']);



        $teamIds = collect($request->input('team_ids', []))

            ->map(fn ($id) => (int) $id)

            ->filter()

            ->unique()

            ->values();



        abort_if($teamIds->isEmpty(), 422, 'Elegí al menos un equipo.');



        $teams = Team::query()

            ->where('category_id', $category->id)

            ->whereIn('id', $teamIds)

            ->with(['players' => fn ($query) => $query->orderBy('last_name')->orderBy('first_name')])

            ->orderBy('name')

            ->get();



        abort_if($teams->isEmpty(), 404, 'No se encontraron equipos válidos.');



        $stats = $this->playerEventStats($category);



        return ReportPdf::render($request, 'workspace.reports.players', [

            'category' => $category,

            'tournament' => $category->tournament,

            'teams' => $teams,

            'stats' => $stats,

        ], 'jugadores-cat-'.$category->id, 'portrait');

    }



    public function credentials(Request $request, Category $category): View|Response

    {

        $this->assertReportsAccess($request, $category);

        $category->load(['tournament']);



        $teamIds = collect($request->input('team_ids', []))

            ->map(fn ($id) => (int) $id)

            ->filter()

            ->unique()

            ->values();



        abort_if($teamIds->isEmpty(), 422, 'Elegí al menos un equipo.');



        $players = Player::query()

            ->whereHas('team', fn ($query) => $query->where('category_id', $category->id)->whereIn('id', $teamIds))

            ->with('team')

            ->orderBy('team_id')

            ->orderBy('last_name')

            ->orderBy('first_name')

            ->get();



        abort_if($players->isEmpty(), 404, 'No hay jugadores en los equipos elegidos.');



        return ReportPdf::render($request, 'workspace.reports.credentials', [

            'category' => $category,

            'tournament' => $category->tournament,

            'players' => $players,

        ], 'carnet-cat-'.$category->id, 'landscape');

    }



    public function acta(Request $request, Category $category): View|Response

    {

        $this->assertReportsAccess($request, $category);

        abort_unless($this->canViewPlanillas($request->user()), 403, 'Las actas son solo para el staff del torneo.');



        $category->load(['tournament']);

        $matches = FixtureMatch::query()

            ->with(['homeTeam', 'awayTeam', 'field.venue'])

            ->where('category_id', $category->id)

            ->orderBy('scheduled_at')

            ->get();



        $workspace = $category->workspace();

        $phases = CategoryWorkspace::phaseNames($workspace);

        $rounds = CategoryWorkspace::roundOptions(

            $workspace,

            $matches->map(fn ($match) => CategoryWorkspace::normalizeRound($match->round, $match->stage))->filter()->unique()->values()->all()

        );



        $selectedPhase = $request->string('phase', 'all')->toString() ?: 'all';

        $selectedRound = $request->string('round', 'all')->toString() ?: 'all';



        $filtered = $matches

            ->when($selectedPhase !== 'all', fn ($collection) => $collection->filter(

                fn ($match) => CategoryWorkspace::matchPhase($match, $phases) === $selectedPhase

            ))

            ->when($selectedRound !== 'all', fn ($collection) => $collection->filter(

                fn ($match) => CategoryWorkspace::normalizeRound($match->round, $match->stage) === $selectedRound

            ))

            ->values();



        abort_if($filtered->isEmpty(), 422, 'No hay partidos para la fase/fecha elegida.');



        return ReportPdf::render($request, 'workspace.reports.acta-picker', [

            'category' => $category,

            'tournament' => $category->tournament,

            'matches' => $filtered,

            'selectedPhase' => $selectedPhase,

            'selectedRound' => $selectedRound,

            'phases' => $phases,

            'rounds' => $rounds,

        ], 'actas-cat-'.$category->id, 'portrait');

    }



    private function assertReportsAccess(Request $request, Category $category): void

    {

        $this->assertCategory($category);

        abort_unless($this->canEdit($request->user()), 403, 'Solo administración puede imprimir reportes de la categoría.');

    }



    /**

     * @return Collection<int, array{cards: int, assists: int, goals: int}>

     */

    private function playerEventStats(Category $category): Collection

    {

        $stats = [];



        MatchSheetEvent::query()

            ->whereHas('sheet.match', fn ($query) => $query

                ->where('category_id', $category->id)

                ->whereIn('status', ['finished', 'validated']))

            ->whereNotNull('player_id')

            ->get(['player_id', 'type'])

            ->each(function (MatchSheetEvent $event) use (&$stats) {

                $id = (int) $event->player_id;

                $stats[$id] ??= ['cards' => 0, 'assists' => 0, 'goals' => 0];



                match ($event->type) {

                    'yellow', 'red' => $stats[$id]['cards']++,

                    'assist' => $stats[$id]['assists']++,

                    'goal' => $stats[$id]['goals']++,

                    default => null,

                };

            });



        return collect($stats);

    }

}


