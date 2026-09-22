<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryPoll;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspacePollsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_demo_poll_in_rankings(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.categories.rankings', $category))
            ->assertOk()
            ->assertSee('Encuestas', false)
            ->assertSee('¿Quién fue el mejor jugador de la fecha?', false)
            ->assertSee('Mateo Díaz', false)
            ->assertSee('ws-poll-card', false);
    }

    public function test_admin_can_create_poll(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('workspace.categories.polls.store', $category), [
                'question' => 'Mejor arquero del torneo',
                'options' => "Juan Pérez\nPedro Gómez",
                'is_visible' => '1',
                'show_results' => '1',
                'voting_open' => '1',
            ])
            ->assertRedirect(route('workspace.categories.rankings', $category));

        $poll = CategoryPoll::query()->where('question', 'Mejor arquero del torneo')->firstOrFail();
        $this->assertSame(2, $poll->options()->count());
    }

    public function test_delegate_can_vote_but_not_create_poll(): void
    {
        $this->seed(DatabaseSeeder::class);

        $delegate = User::query()->where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();
        $poll = CategoryPoll::query()->where('category_id', $category->id)->firstOrFail();
        $optionId = $poll->options()->value('id');

        $this->actingAs($delegate)
            ->post(route('workspace.categories.polls.store', $category), [
                'question' => 'No permitida',
                'options' => "A\nB",
            ])
            ->assertForbidden();

        $this->actingAs($delegate)
            ->post(route('workspace.categories.polls.vote', [$category, $poll]), [
                'option_ids' => [$optionId],
            ])
            ->assertRedirect(route('workspace.categories.rankings', $category));

        $this->actingAs($delegate)
            ->post(route('workspace.categories.polls.vote', [$category, $poll]), [
                'option_ids' => [$optionId],
            ])
            ->assertStatus(422);
    }

    public function test_hidden_poll_is_not_visible_to_delegate(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@stctorneos.demo')->firstOrFail();
        $delegate = User::query()->where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();

        $poll = CategoryPoll::create([
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'question' => 'Encuesta interna oculta',
            'is_visible' => false,
            'show_results' => false,
            'voting_open' => true,
            'allow_multiple' => false,
        ]);
        $poll->syncOptions(['Opción A', 'Opción B']);

        $this->actingAs($admin)
            ->get(route('workspace.categories.rankings', $category))
            ->assertOk()
            ->assertSee('Encuesta interna oculta', false);

        $this->actingAs($delegate)
            ->get(route('workspace.categories.rankings', $category))
            ->assertOk()
            ->assertDontSee('Encuesta interna oculta', false);
    }
}
