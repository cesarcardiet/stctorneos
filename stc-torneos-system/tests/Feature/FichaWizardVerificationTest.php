<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\User;
use App\Support\PlayerCredentials;
use App\Support\TutorCredentials;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FichaPlayerAccessDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FichaWizardVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ficha_wizard_shows_nine_steps_and_player_email_on_step_nine(): void
    {
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
        $this->seed(FichaPlayerAccessDemoSeeder::class);

        $player = Player::query()
            ->where('first_name', 'Tomás')
            ->where('last_name', 'FichaDemo')
            ->firstOrFail();

        $tutor = User::query()->where('email', FichaPlayerAccessDemoSeeder::TUTOR_EMAIL)->firstOrFail();

        $response = $this->actingAs($tutor)
            ->get(route('workspace.tutor.ficha', $player));

        $response->assertOk();

        foreach (range(1, 9) as $step) {
            $response->assertSee('data-step="'.$step.'"', false);
        }

        $response
            ->assertSee('Paso 9 · Revisión y envío', false)
            ->assertSee('Acceso al portal del jugador', false)
            ->assertSee('name="player_email"', false)
            ->assertSee('paso 9 · Enviar', false);
    }

    public function test_seeded_verified_player_can_login_to_portal(): void
    {
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
        $this->seed(FichaPlayerAccessDemoSeeder::class);

        $playerUser = User::query()->where('email', FichaPlayerAccessDemoSeeder::PLAYER_EMAIL)->firstOrFail();
        $this->assertTrue($playerUser->isPlayerAccount());
        $this->assertTrue(Hash::check(PlayerCredentials::defaultPassword(), $playerUser->password));

        $this->post(route('login.perform'), [
            'email' => FichaPlayerAccessDemoSeeder::PLAYER_EMAIL,
            'password' => PlayerCredentials::defaultPassword(),
        ])->assertRedirect(route('workspace.player.home'));
    }

    public function test_tutor_can_complete_pending_ficha_with_player_email_from_seeder(): void
    {
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
        $this->seed(FichaPlayerAccessDemoSeeder::class);

        $player = Player::query()
            ->where('first_name', 'Tomás')
            ->where('last_name', 'FichaDemo')
            ->firstOrFail();

        $tutor = User::query()->where('email', FichaPlayerAccessDemoSeeder::TUTOR_EMAIL)->firstOrFail();
        $this->assertTrue(Hash::check(TutorCredentials::defaultPassword(), $tutor->password));

        $documentFiles = [];
        foreach (Player::tutorUploadDocumentTypes() as $type) {
            $documentFiles[$type] = UploadedFile::fake()->image(str_replace(' ', '-', strtolower($type)).'.jpg');
        }

        $this->actingAs($tutor)
            ->post(route('workspace.tutor.ficha.update', $player), [
                'first_name' => 'Tomás',
                'last_name' => 'FichaDemo',
                'guardian_first_name' => 'Carolina',
                'guardian_last_name' => 'FichaDemo',
                'guardian_name' => 'Carolina FichaDemo',
                'guardian_document_number' => '30111222',
                'relationship' => 'Madre',
                'email' => FichaPlayerAccessDemoSeeder::TUTOR_EMAIL,
                'player_email' => 'tomas.jugador@stc.test',
                'phone' => '+54 11 5555-9901',
                'guardian_alternate_name' => 'Pedro FichaDemo',
                'guardian_alternate_phone' => '+54 11 5555-9902',
                'document_number' => '50999001',
                'birth_date' => '2014-06-15',
                'nationality' => 'Argentina',
                'address' => 'Av. Demo 123, CABA',
                'kit_size' => 'M',
                'height' => '1.52',
                'weight' => '44',
                'blood_type' => 'O+',
                'medical_coverage' => 'OSDE',
                'allergies' => 'Ninguna',
                'medication' => 'Ninguna',
                'illnesses' => 'Ninguna',
                'restrictions' => 'Ninguna',
                'emergency_contact' => 'Carolina +54 11 5555-9901',
                'vaccination_calendar_complete' => '1',
                'consent' => '1',
                'complete' => '1',
                'auth' => ['Autorización', 'Uso de imagen', 'Apto médico'],
                'document_files' => $documentFiles,
            ])
            ->assertRedirect(route('workspace.tutor.ficha', $player));

        $player->refresh();
        $this->assertSame('submitted', $player->status);
        $this->assertSame('tomas.jugador@stc.test', $player->email);

        $created = User::query()->where('email', 'tomas.jugador@stc.test')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->isPlayerAccount());
        $this->assertTrue(Hash::check(PlayerCredentials::defaultPassword(), $created->password));
    }
}
