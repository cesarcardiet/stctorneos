<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Services\GuardianInvitationService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FichaPlayerAccessDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MobileTutorFichaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutor_can_load_and_submit_ficha_via_api(): void
    {
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
        $this->seed(FichaPlayerAccessDemoSeeder::class);

        $player = Player::query()
            ->where('first_name', 'Tomás')
            ->where('last_name', 'FichaDemo')
            ->firstOrFail();

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => FichaPlayerAccessDemoSeeder::TUTOR_EMAIL,
            'password' => 'stctutor',
        ])->assertOk()
            ->json('data.token');

        $this->withToken($token)
            ->getJson("/api/v1/tutor/players/{$player->id}/ficha")
            ->assertOk()
            ->assertJsonPath('data.locked', false)
            ->assertJsonStructure([
                'data' => [
                    'player',
                    'guardian',
                    'documents',
                    'options',
                    'authorization_texts',
                ],
            ]);

        $documentFiles = [];
        foreach (Player::tutorUploadDocumentTypes() as $type) {
            $documentFiles[$type] = UploadedFile::fake()->image(str_replace(' ', '-', strtolower($type)).'.jpg');
        }

        $this->withToken($token)
            ->post("/api/v1/tutor/players/{$player->id}/ficha", array_merge([
                'document_files' => $documentFiles,
                'first_name' => 'Juan',
                'last_name' => 'Prueba',
                'guardian_first_name' => 'María',
                'guardian_last_name' => 'Ruiz',
                'guardian_name' => 'María Ruiz',
                'guardian_document_number' => '30123456',
                'relationship' => 'Madre',
                'email' => FichaPlayerAccessDemoSeeder::TUTOR_EMAIL,
                'phone' => '+54 11 5555-0000',
                'guardian_alternate_name' => 'Pedro Ruiz',
                'guardian_alternate_phone' => '+54 11 5555-1111',
                'guardian_alternate_contact' => 'Pedro Ruiz · +54 11 5555-1111',
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
                'emergency_contact' => 'María Ruiz +54 11 5555-0000',
                'vaccination_calendar_complete' => '1',
                'consent' => '1',
                'complete' => '1',
                'auth' => ['Autorización', 'Uso de imagen', 'Apto médico'],
            ]), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.locked', true);

        $player->refresh();
        $this->assertSame('submitted', $player->status);
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
