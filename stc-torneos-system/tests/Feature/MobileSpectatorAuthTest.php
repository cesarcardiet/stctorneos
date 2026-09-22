<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileSpectatorAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_spectator_can_register_and_update_profile(): void
    {
        $this->seed(DatabaseSeeder::class);

        $register = $this->postJson('/api/v1/auth/register/spectator', [
            'name' => 'Juan Espectador',
            'email' => 'espectador@test.com',
            'password' => 'Segura123!',
            'password_confirmation' => 'Segura123!',
            'device_name' => 'Pixel Test',
        ])->assertCreated()
            ->assertJsonPath('data.user.role_slug', 'consulta')
            ->assertJsonPath('data.user.status', 'active');

        $token = $register->json('data.token');

        $this->withToken($token)
            ->patchJson('/api/v1/me', [
                'name' => 'Juan Martín Gómez',
                'phone' => '+54 9 11 5555-4444',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Juan Martín Gómez')
            ->assertJsonPath('data.phone', '+54 9 11 5555-4444');
    }

    public function test_spectator_register_rejects_duplicate_email(): void
    {
        $this->seed(DatabaseSeeder::class);
        User::factory()->create(['email' => 'duplicado@test.com']);

        $this->postJson('/api/v1/auth/register/spectator', [
            'name' => 'Otro Usuario',
            'email' => 'duplicado@test.com',
            'password' => 'Segura123!',
            'password_confirmation' => 'Segura123!',
        ])->assertStatus(422);
    }
}
