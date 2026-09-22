<?php

namespace Tests\Feature;

use App\Models\Delegation;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FichaPlayerAccessDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileInvitationAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_returns_invitation_details(): void
    {
        $this->seed(DatabaseSeeder::class);
        $invitation = $this->createStaffInvitation();

        $this->postJson('/api/v1/auth/invitations/preview', [
            'email' => $invitation->email,
            'invitation_code' => $invitation->code,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.email', $invitation->email)
            ->assertJsonPath('data.role.slug', 'delegado')
            ->assertJsonPath('data.mode', 'register');
    }

    public function test_accept_creates_user_with_delegation_scope(): void
    {
        $this->seed(DatabaseSeeder::class);
        $invitation = $this->createStaffInvitation();

        $response = $this->postJson('/api/v1/auth/invitations/accept', [
            'name' => 'Nuevo Delegado',
            'email' => $invitation->email,
            'invitation_code' => $invitation->code,
            'password' => 'Segura123!',
            'password_confirmation' => 'Segura123!',
            'device_name' => 'Pixel Test',
        ])->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.user.email', $invitation->email)
            ->assertJsonPath('data.user.status', 'active');

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);

        $user = User::query()->where('email', $invitation->email)->firstOrFail();
        $this->assertTrue(Hash::check('Segura123!', $user->password));
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->delegation_id);
        $this->assertTrue($user->assignedDelegations()->exists());

        $invitation->refresh();
        $this->assertSame('accepted', $invitation->status);
        $this->assertNotNull($invitation->accepted_at);
    }

    public function test_accept_activates_precreate_admin_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $role = Role::query()->where('slug', 'asistente-mesa')->firstOrFail();
        $tournament = Tournament::query()->firstOrFail();

        $user = User::create([
            'name' => 'Mesa Pendiente',
            'email' => 'mesa.nueva@stctorneos.demo',
            'password' => Hash::make('temporal123'),
            'status' => 'pending',
            'tournament_id' => $tournament->id,
        ]);

        $user->roles()->sync([
            $role->id => [
                'scope_type' => 'tournament',
                'scope_id' => $tournament->id,
                'assigned_at' => now(),
            ],
        ]);

        $invitation = Invitation::create([
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $role->id,
            'status' => 'pending',
            'token' => Invitation::makeToken(),
            'scope_type' => 'tournament',
            'scope_id' => $tournament->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->postJson('/api/v1/auth/invitations/accept', [
            'email' => $invitation->email,
            'invitation_code' => $invitation->code,
            'password' => 'NuevaClave1!',
            'password_confirmation' => 'NuevaClave1!',
        ])->assertCreated()
            ->assertJsonPath('data.user.status', 'active');

        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_expired_invitation_is_rejected(): void
    {
        $this->seed(DatabaseSeeder::class);
        $invitation = $this->createStaffInvitation(expired: true);

        $this->postJson('/api/v1/auth/invitations/preview', [
            'email' => $invitation->email,
            'invitation_code' => $invitation->code,
        ])->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_login_and_me_return_enriched_payload(): void
    {
        $this->seed(DatabaseSeeder::class);
        $invitation = $this->createStaffInvitation();

        $this->postJson('/api/v1/auth/invitations/accept', [
            'name' => 'Delegado App',
            'email' => $invitation->email,
            'invitation_code' => $invitation->code,
            'password' => 'Segura123!',
            'password_confirmation' => 'Segura123!',
        ])->assertCreated();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $invitation->email,
            'password' => 'Segura123!',
        ])->assertOk()
            ->assertJsonPath('data.user.roles.0.slug', 'delegado')
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'permissions',
                        'capabilities',
                        'navigation',
                        'context',
                    ],
                ],
            ]);

        $token = $login->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.role_slug', 'delegado')
            ->assertJsonFragment(['key' => 'home']);
    }

    public function test_mesa_demo_can_login_and_access_staff_matches(): void
    {
        $this->seed(DatabaseSeeder::class);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'mesa@stctorneos.demo',
            'password' => 'stcdemo',
        ])->assertOk()
            ->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v1/staff/matches')
            ->assertOk();
    }

    public function test_guardian_invitation_preview_returns_tutor_role(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(FichaPlayerAccessDemoSeeder::class);

        $invitation = Invitation::query()
            ->where('kind', 'guardian')
            ->where('email', FichaPlayerAccessDemoSeeder::TUTOR_EMAIL)
            ->whereNull('accepted_at')
            ->latest('id')
            ->firstOrFail();

        $this->postJson('/api/v1/auth/invitations/preview', [
            'email' => $invitation->email,
            'invitation_code' => $invitation->token,
        ])
            ->assertOk()
            ->assertJsonPath('data.kind', 'guardian')
            ->assertJsonPath('data.role.slug', 'tutor');
    }

    private function createStaffInvitation(bool $expired = false): Invitation
    {
        $role = Role::query()->where('slug', 'delegado')->firstOrFail();
        $club = Delegation::query()->firstOrFail();

        return Invitation::create([
            'name' => 'Invitado Demo',
            'email' => 'invitado.'.uniqid().'@stctorneos.demo',
            'role_id' => $role->id,
            'status' => 'pending',
            'kind' => 'staff',
            'token' => Invitation::makeToken(),
            'scope_type' => 'delegation',
            'scope_id' => $club->id,
            'expires_at' => $expired ? now()->subDay() : now()->addDays(7),
        ]);
    }
}
