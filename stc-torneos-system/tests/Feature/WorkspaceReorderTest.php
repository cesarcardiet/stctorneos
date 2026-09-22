<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FixtureMatch;
use App\Models\Team;
use App\Models\User;
use App\Services\CompetitionBoard;
use App\Support\CategoryWorkspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reorder_teams_and_standings_reflect_manual_order(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();
        $sample = Team::query()->where('category_id', $category->id)->firstOrFail();
        $teams = Team::query()
            ->where('category_id', $category->id)
            ->where('group_name', $sample->group_name)
            ->orderBy('name')
            ->take(3)
            ->get();
        $this->assertGreaterThanOrEqual(2, $teams->count());

        $reversed = $teams->pluck('id')->reverse()->values()->all();
        $groupKey = CategoryWorkspace::teamOrderStorageKey($teams->first()->group_name);

        $this->actingAs($admin)
            ->postJson(route('workspace.categories.teams.reorder', $category), [
                'group_name' => $teams->first()->group_name,
                'team_ids' => $reversed,
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'group_key' => $groupKey]);

        $category->refresh();
        $groups = app(CompetitionBoard::class)->standingsByGroup($category);
        $groupLabel = $teams->first()->group_name ? 'Grupo '.$teams->first()->group_name : 'Sin grupo';
        $orderedNames = $groups->get($groupLabel, collect())->pluck('team.name')->values()->all();

        $expectedNames = Team::query()->whereIn('id', $reversed)->get()->keyBy('id');
        $expectedOrder = collect($reversed)->map(fn (int $id) => $expectedNames[$id]->name)->all();
        $actualOrder = array_slice($orderedNames, 0, count($reversed));

        $this->assertSame($expectedOrder, $actualOrder);
    }

    public function test_admin_can_reorder_matches_for_current_scope(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();
        $matches = FixtureMatch::query()->where('category_id', $category->id)->orderBy('id')->take(2)->get();
        $this->assertGreaterThanOrEqual(2, $matches->count());

        $reversed = $matches->pluck('id')->reverse()->values()->all();

        $this->actingAs($admin)
            ->postJson(route('workspace.categories.matches.reorder', $category), [
                'phase' => 'all',
                'round' => 'all',
                'match_ids' => $reversed,
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $category->refresh();
        $saved = $category->workspace()['match_order']['all|all'] ?? [];
        $this->assertSame($reversed, $saved);
    }

    public function test_delegate_cannot_reorder_teams_or_matches(): void
    {
        $this->seed(DatabaseSeeder::class);

        $delegate = User::query()->where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();
        $team = Team::query()->where('category_id', $category->id)->firstOrFail();
        $match = FixtureMatch::query()->where('category_id', $category->id)->firstOrFail();

        $this->actingAs($delegate)
            ->postJson(route('workspace.categories.teams.reorder', $category), [
                'group_name' => $team->group_name,
                'team_ids' => [$team->id],
            ])
            ->assertForbidden();

        $this->actingAs($delegate)
            ->postJson(route('workspace.categories.matches.reorder', $category), [
                'phase' => 'all',
                'round' => 'all',
                'match_ids' => [$match->id],
            ])
            ->assertForbidden();
    }

    public function test_standings_page_includes_reorder_modals_for_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.categories.standings', $category))
            ->assertOk()
            ->assertSee('data-ws-open="reorder-teams"', false)
            ->assertSee('data-ws-open="reorder-matches"', false)
            ->assertSee('data-ws-reorder-teams', false)
            ->assertSee('data-ws-reorder-matches', false)
            ->assertSee('data-ws-standings-sort', false);
    }
}
