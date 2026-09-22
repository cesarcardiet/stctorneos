<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MobilePlayerRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_delegate_can_create_update_and_invite_guardian_for_player(): void
    {
        Mail::fake();
        $this->seed(DatabaseSeeder::class);

        $delegate = User::where('email', 'delegado@stctorneos.demo')->firstOrFail();
        $category = Category::where('name', 'Sub 12 Masculino')->firstOrFail();
        $team = Team::where('name', 'Leones FC')->firstOrFail();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $delegate->email,
            'password' => 'stcdemo',
        ])->assertOk();

        $token = $login->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v1/roster/context')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Leones FC'])
            ->assertJsonPath('data.options.positions.0', 'Arquero');

        $create = $this->withToken($token)
            ->postJson("/api/v1/categories/{$category->id}/players", [
                'team_id' => $team->id,
                'first_name' => 'Mateo',
                'last_name' => 'Mobile Test',
                'document_number' => '50111222',
                'birth_date' => '2014-03-15',
                'position' => 'Delantero',
                'jersey_number' => 9,
                'kit_size' => '12',
                'preferred_foot' => 'Derecha',
            ])
            ->assertCreated()
            ->assertJsonPath('data.first_name', 'Mateo')
            ->assertJsonPath('data.last_name', 'Mobile Test');

        $playerId = $create->json('data.id');

        $this->withToken($token)
            ->patchJson("/api/v1/categories/{$category->id}/players/{$playerId}", [
                'blood_type' => 'O+',
                'medical_coverage' => 'OSDE 210',
                'allergies' => 'Ninguna',
                'emergency_contact' => 'María Test · 11 4444-5555',
                'guardian_name' => 'María Test',
                'guardian_relationship' => 'Madre',
                'guardian_phone' => '11 4444-5555',
                'guardian_email' => 'maria.tutor.mobile@test.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.blood_type', 'O+')
            ->assertJsonPath('data.guardian.name', 'María Test');

        $this->withToken($token)
            ->post("/api/v1/categories/{$category->id}/players/{$playerId}/documents", [
                'type' => 'DNI frente',
                'file' => UploadedFile::fake()->image('dni-frente.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.document.type', 'DNI frente');

        $this->withToken($token)
            ->postJson("/api/v1/categories/{$category->id}/players/{$playerId}/invite-guardian", [
                'email' => 'maria.tutor.mobile@test.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.invitation.email', 'maria.tutor.mobile@test.com')
            ->assertJsonPath('data.player.layers.guardian', 'Enlace enviado');

        $this->withToken($token)
            ->getJson("/api/v1/categories/{$category->id}/players/{$playerId}/roster")
            ->assertOk()
            ->assertJsonPath('data.name', 'Mateo Mobile Test');
    }
}
