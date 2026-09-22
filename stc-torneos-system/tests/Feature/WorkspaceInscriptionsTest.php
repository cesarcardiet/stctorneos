<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceInscriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_inscription_bands_with_waiting_and_rejected(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('workspace.categories.inscriptions', $category))
            ->assertOk()
            ->assertSee('Esperando aprobación', false)
            ->assertSee('Rechazado', false)
            ->assertSee('Mateo Díaz', false)
            ->assertSee('Valentín Gómez', false)
            ->assertSee('Aprobar ficha', false)
            ->assertSee('Ver ficha', false)
            ->assertSee('Editar ficha', false)
            ->assertSee('DNI frente', false)
            ->assertSee('ws-inscription-doc-card', false);
    }

    public function test_admin_can_approve_waiting_inscription(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();
        $player = Player::query()->where('first_name', 'Mateo')->where('last_name', 'Díaz')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('workspace.categories.inscriptions.review', [$category, $player]), [
                'decision' => 'approve',
            ])
            ->assertRedirect(route('workspace.categories.inscriptions', $category));

        $this->assertSame('approved', $player->fresh()->status);
    }

    public function test_delegate_sees_inscriptions_for_own_club_only(): void
    {
        $this->seed(DatabaseSeeder::class);

        $delegate = User::query()->where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();
        $team = Team::query()->where('name', 'Leones FC')->where('category_id', $category->id)->firstOrFail();

        Player::query()->where('team_id', $team->id)->update(['status' => 'approved']);
        Player::query()->create([
            'team_id' => $team->id,
            'first_name' => 'Pedro',
            'last_name' => 'Pendiente',
            'status' => 'pending',
            'document_number' => '49998877',
            'birth_date' => '2014-01-01',
            'nationality' => 'Argentina',
        ]);

        $this->actingAs($delegate)
            ->get(route('workspace.categories.inscriptions', $category))
            ->assertOk()
            ->assertSee('Pedro Pendiente', false)
            ->assertDontSee('Mateo Díaz', false)
            ->assertDontSee('Aprobar', false);
    }

    public function test_delegate_cannot_review_inscriptions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $delegate = User::query()->where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();
        $player = Player::query()->where('first_name', 'Mateo')->where('last_name', 'Díaz')->firstOrFail();

        $this->actingAs($delegate)
            ->post(route('workspace.categories.inscriptions.review', [$category, $player]), [
                'decision' => 'approve',
            ])
            ->assertForbidden();
    }

    public function test_match_staff_cannot_open_inscriptions_inbox(): void
    {
        $this->seed(DatabaseSeeder::class);

        $referee = User::query()->where('email', 'coordinador@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();

        $this->actingAs($referee)
            ->get(route('workspace.categories.inscriptions', $category))
            ->assertForbidden();
    }
}
