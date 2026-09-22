<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Guardian;
use App\Models\Player;
use App\Models\Role;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Services\GuardianInvitationService;
use App\Support\TutorCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TutorAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_tutor_ficha_is_locked_and_cannot_be_edited(): void
    {
        Mail::fake();
        $this->seed();

        $player = $this->samplePlayer();
        Guardian::updateOrCreate(
            ['player_id' => $player->id],
            ['name' => 'María López', 'email' => 'maria.tutor@test.stc', 'consent_status' => 'pending']
        );

        $tutor = User::query()->where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $invitation = app(GuardianInvitationService::class)->create(
            $player->fresh('guardian'),
            'maria.tutor@test.stc',
            $tutor->id
        );

        $invitation->update(['family_status' => 'completed', 'status' => 'accepted']);
        $player->update(['status' => 'submitted']);

        $tutorAccount = User::query()->where('email', 'maria.tutor@test.stc')->firstOrFail();

        $this->actingAs($tutorAccount)
            ->get(route('workspace.tutor.ficha', $player))
            ->assertOk()
            ->assertSee('Ficha enviada · En revisión')
            ->assertDontSee('data-ficha-wizard', false);

        $this->actingAs($tutorAccount)
            ->post(route('workspace.tutor.ficha.update', $player), [
                'first_name' => 'Juan',
                'last_name' => 'Prueba',
                'complete' => '1',
            ])
            ->assertRedirect(route('workspace.tutor.ficha', $player))
            ->assertSessionHasErrors('ficha');
    }

    public function test_tutor_can_open_ficha_from_panel_without_prior_invite(): void
    {
        $this->seed();

        $player = $this->samplePlayer();
        app(GuardianInvitationService::class)->ensureGuardianFromPlayerForm($player, [
            'guardian_name' => 'Gladys Gauto',
            'guardian_email' => 'stcfabiola@gmail.com',
        ]);

        $tutor = User::query()->where('email', 'stcfabiola@gmail.com')->firstOrFail();

        $this->actingAs($tutor)
            ->get(route('workspace.tutor.ficha', $player))
            ->assertOk()
            ->assertSee('Paso 1 de 9')
            ->assertSee('Confirmar datos del delegado');

        $this->assertDatabaseHas('invitations', [
            'player_id' => $player->id,
            'email' => 'stcfabiola@gmail.com',
            'kind' => 'guardian',
        ]);
    }

    public function test_saving_guardian_email_creates_tutor_account_without_invite(): void
    {
        $this->seed();

        $player = $this->samplePlayer();

        app(GuardianInvitationService::class)->ensureGuardianFromPlayerForm($player, [
            'guardian_name' => 'Gladys Gauto',
            'guardian_email' => 'stcfabiola@gmail.com',
        ]);

        $tutor = User::query()->where('email', 'stcfabiola@gmail.com')->first();
        $this->assertNotNull($tutor);
        $this->assertTrue($tutor->isTutorAccount());
        $this->assertTrue(Hash::check(TutorCredentials::defaultPassword(), $tutor->password));

        $this->post('/login', [
            'email' => 'stcfabiola@gmail.com',
            'password' => TutorCredentials::defaultPassword(),
        ])->assertRedirect(route('workspace.tutor.home'));
    }

    public function test_inviting_guardian_creates_tutor_login_with_default_password(): void
    {
        Mail::fake();

        $this->seed();

        $player = $this->samplePlayer();
        Guardian::updateOrCreate(
            ['player_id' => $player->id],
            ['name' => 'María López', 'email' => 'maria.tutor@test.stc', 'consent_status' => 'pending']
        );

        $delegate = User::where('email', 'delegado@stctorneos.demo')->firstOrFail();

        $invitation = app(GuardianInvitationService::class)->create(
            $player->fresh('guardian'),
            'maria.tutor@test.stc',
            $delegate->id
        );

        $this->assertNotNull($invitation->publicUrl());

        $tutor = User::query()->where('email', 'maria.tutor@test.stc')->first();
        $this->assertNotNull($tutor);
        $this->assertTrue($tutor->isTutorAccount());
        $this->assertTrue(Hash::check(TutorCredentials::defaultPassword(), $tutor->password));

        $this->post('/login', [
            'email' => 'maria.tutor@test.stc',
            'password' => TutorCredentials::defaultPassword(),
        ])->assertRedirect(route('workspace.tutor.home'));

        $this->actingAs($tutor)
            ->get(route('workspace.tutor.home'))
            ->assertOk()
            ->assertSee($player->fullName());
    }

    public function test_existing_tutor_keeps_password_on_second_invite(): void
    {
        Mail::fake();
        $this->seed();

        $player = $this->samplePlayer();
        Guardian::updateOrCreate(
            ['player_id' => $player->id],
            ['name' => 'María López', 'email' => 'maria.tutor@test.stc', 'consent_status' => 'pending']
        );

        $delegate = User::where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $service = app(GuardianInvitationService::class);

        $service->create($player->fresh('guardian'), 'maria.tutor@test.stc', $delegate->id);
        $tutor = User::where('email', 'maria.tutor@test.stc')->firstOrFail();
        $tutor->update(['password' => 'MiClaveNueva123']);

        $service->create($player->fresh('guardian'), 'maria.tutor@test.stc', $delegate->id);

        $this->assertTrue(Hash::check('MiClaveNueva123', $tutor->fresh()->password));
        $this->assertFalse(Hash::check(TutorCredentials::defaultPassword(), $tutor->fresh()->password));
    }

    public function test_tutor_can_browse_tournament_read_only(): void
    {
        $this->seed();

        $tutor = User::query()->where('email', 'tutor@stctorneos.demo')->firstOrFail();
        $category = Category::query()->where('name', 'Sub 12 Masculino')->firstOrFail();
        $otherTournament = Tournament::query()->where('slug', 'stc-buenos-aires-2026')->firstOrFail();
        $access = \App\Support\WorkspaceAccess::for($tutor);

        $this->assertTrue($access->isTutor());
        $this->assertTrue($access->canBrowseAllTournaments());
        $this->assertTrue($tutor->canAccessTournament((int) $otherTournament->id));
        $this->assertGreaterThanOrEqual(2, $tutor->accessibleTournaments()->count());
        $this->assertTrue($access->canViewCategoryCompetition($category));
        $this->assertTrue($access->canViewRankings());
        $this->assertTrue($access->canViewMedia());
        $this->assertFalse($access->canViewSettings());
        $this->assertFalse($access->canManageTeamRoster(Team::where('category_id', $category->id)->firstOrFail()));

        $this->actingAs($tutor)
            ->get(route('workspace.home'))
            ->assertOk()
            ->assertSee('Santa Teresita Cup 2026')
            ->assertSee('STC Buenos Aires 2026');

        $this->actingAs($tutor)
            ->get(route('workspace.tournaments.show', $otherTournament))
            ->assertOk();

        $this->actingAs($tutor)
            ->get(route('workspace.categories.standings', $category))
            ->assertOk();

        $this->actingAs($tutor)
            ->get(route('workspace.categories.rankings', $category))
            ->assertOk();

        $this->actingAs($tutor)
            ->get(route('workspace.categories.settings', $category))
            ->assertForbidden();

        $this->actingAs($tutor)
            ->post(route('workspace.categories.teams.store', $category), ['name' => 'Equipo tutor'])
            ->assertForbidden();
    }

    private function samplePlayer(): Player
    {
        $category = Category::query()->whereHas('teams')->firstOrFail();
        $team = Team::query()->where('category_id', $category->id)->firstOrFail();

        return Player::create([
            'team_id' => $team->id,
            'first_name' => 'Juan',
            'last_name' => 'Prueba',
            'status' => 'pending',
        ]);
    }
}
