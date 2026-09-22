<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Player;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\GuardianInvitationService;
use App\Support\PlayerCredentials;
use App\Support\TutorCredentials;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlayerAccountFromFichaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutor_ficha_submit_creates_player_account_when_player_email_is_provided(): void
    {
        Mail::fake();
        $this->seed(DatabaseSeeder::class);

        $player = $this->samplePlayer();
        Guardian::updateOrCreate(
            ['player_id' => $player->id],
            ['name' => 'María López', 'email' => 'maria.tutor@test.stc', 'consent_status' => 'pending']
        );

        $delegate = User::query()->where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $invitation = app(GuardianInvitationService::class)->create(
            $player->fresh('guardian'),
            'maria.tutor@test.stc',
            $delegate->id,
            sendMail: false
        );

        $tutor = User::query()->where('email', 'maria.tutor@test.stc')->firstOrFail();

        $this->actingAs($tutor)
            ->get(route('workspace.tutor.ficha', $player))
            ->assertOk()
            ->assertSee('Acceso al portal del jugador', false)
            ->assertSee('name="player_email"', false);

        $this->actingAs($tutor)
            ->post(route('workspace.tutor.ficha.update', $player), $this->validPayload([
                'email' => 'maria.tutor@test.stc',
                'player_email' => 'lautaro.jugador@test.stc',
            ]))
            ->assertRedirect(route('workspace.tutor.ficha', $player));

        $player->refresh();
        $this->assertSame('lautaro.jugador@test.stc', $player->email);
        $this->assertSame('submitted', $player->status);

        $playerUser = User::query()->where('email', 'lautaro.jugador@test.stc')->first();
        $this->assertNotNull($playerUser);
        $this->assertTrue($playerUser->isPlayerAccount());
        $this->assertSame($player->id, $playerUser->player_id);
        $this->assertTrue(Hash::check(PlayerCredentials::defaultPassword(), $playerUser->password));

        $this->post(route('login.perform'), [
            'email' => 'lautaro.jugador@test.stc',
            'password' => PlayerCredentials::defaultPassword(),
        ])->assertRedirect(route('workspace.player.home'));
    }

    public function test_tutor_and_player_can_share_email_with_both_roles(): void
    {
        Mail::fake();
        $this->seed(DatabaseSeeder::class);

        $player = $this->samplePlayer();
        Guardian::updateOrCreate(
            ['player_id' => $player->id],
            ['name' => 'María López', 'email' => 'familia@test.stc', 'consent_status' => 'pending']
        );

        $delegate = User::query()->where('email', 'delegado@stctorneos.demo')->firstOrFail();
        app(GuardianInvitationService::class)->create(
            $player->fresh('guardian'),
            'familia@test.stc',
            $delegate->id,
            sendMail: false
        );

        $tutor = User::query()->where('email', 'familia@test.stc')->firstOrFail();

        $this->actingAs($tutor)
            ->post(route('workspace.tutor.ficha.update', $player), $this->validPayload([
                'email' => 'familia@test.stc',
                'player_email' => 'familia@test.stc',
            ]))
            ->assertRedirect(route('workspace.tutor.ficha', $player));

        $account = User::query()->where('email', 'familia@test.stc')->with('roles')->firstOrFail();
        $this->assertTrue($account->isTutorAccount());
        $this->assertTrue($account->isPlayerAccount());
        $this->assertSame($player->id, $account->player_id);
    }

    public function test_tutor_ficha_submit_does_not_crash_when_player_email_is_already_staff(): void
    {
        Mail::fake();
        $this->seed(DatabaseSeeder::class);

        $player = $this->samplePlayer();
        Guardian::updateOrCreate(
            ['player_id' => $player->id],
            ['name' => 'María López', 'email' => 'maria.tutor@test.stc', 'consent_status' => 'pending']
        );

        $delegate = User::query()->where('email', 'delegado@stctorneos.demo')->firstOrFail();
        app(GuardianInvitationService::class)->create(
            $player->fresh('guardian'),
            'maria.tutor@test.stc',
            $delegate->id,
            sendMail: false
        );

        $tutor = User::query()->where('email', 'maria.tutor@test.stc')->firstOrFail();

        $this->actingAs($tutor)
            ->from(route('workspace.tutor.ficha', $player))
            ->post(route('workspace.tutor.ficha.update', $player), $this->validPayload([
                'email' => 'maria.tutor@test.stc',
                'player_email' => 'delegado@stctorneos.demo',
            ]))
            ->assertRedirect(route('workspace.tutor.ficha', $player))
            ->assertSessionHasErrors('player_email');

        $player->refresh();
        $this->assertNotSame('submitted', $player->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        $documentFiles = [];
        foreach (Player::tutorUploadDocumentTypes() as $type) {
            $documentFiles[$type] = UploadedFile::fake()->image(str_replace(' ', '-', strtolower($type)).'.jpg');
        }

        return array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Prueba',
            'guardian_first_name' => 'María',
            'guardian_last_name' => 'López',
            'guardian_name' => 'María López',
            'guardian_document_number' => '30123456',
            'relationship' => 'Madre',
            'phone' => '+54 11 5555-0000',
            'guardian_alternate_name' => 'Pedro López',
            'guardian_alternate_phone' => '+54 11 5555-1111',
            'guardian_alternate_contact' => 'Pedro López · +54 11 5555-1111',
            'document_number' => '45123456',
            'birth_date' => '2012-05-10',
            'nationality' => 'Argentina',
            'address' => 'Calle Falsa 123',
            'kit_size' => 'M',
            'height' => '1.50',
            'weight' => '45',
            'blood_type' => 'O+',
            'medical_coverage' => 'OSDE',
            'allergies' => 'Ninguna',
            'medication' => 'Ninguna',
            'illnesses' => 'Ninguna',
            'restrictions' => 'Ninguna',
            'emergency_contact' => 'María López +54 11 5555-0000',
            'vaccination_calendar_complete' => '1',
            'consent' => '1',
            'complete' => '1',
            'auth' => ['Autorización', 'Uso de imagen', 'Apto médico'],
            'document_files' => $documentFiles,
        ], $overrides);
    }

    private function samplePlayer(): Player
    {
        $team = Team::query()->whereHas('category')->firstOrFail();

        return Player::create([
            'team_id' => $team->id,
            'first_name' => 'Juan',
            'last_name' => 'Prueba',
            'status' => 'pending',
        ]);
    }
}
