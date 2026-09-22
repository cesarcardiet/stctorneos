<?php

namespace Tests\Feature;

use App\Mail\AppNotificationMail;
use App\Mail\GuardianConsentRequestMail;
use App\Mail\UserInvitationMail;
use App\Models\AppNotification;
use App\Models\Player;
use App\Models\Role;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_creation_sends_invitation_mail(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();

        $role = Role::where('slug', 'delegado')->firstOrFail();
        $tournament = Tournament::query()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Usuario Invitado',
            'email' => 'invitado@club.demo',
            'phone' => '+54 11 5555-0000',
            'status' => 'pending',
            'role_id' => $role->id,
            'tournament_id' => $tournament->id,
        ])->assertRedirect();

        Mail::assertSent(UserInvitationMail::class, fn (UserInvitationMail $mail) => $mail->invitation->email === 'invitado@club.demo');
    }

    public function test_password_reset_request_page_is_available(): void
    {
        $this->get(route('password.request'))->assertOk()->assertSee('Recuperar contraseña');
    }

    public function test_notification_send_can_use_email_channel(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        $admin = User::where('email', 'admin@stctorneos.demo')->firstOrFail();

        $notification = AppNotification::create([
            'title' => 'Aviso por correo',
            'body' => 'Mensaje de prueba',
            'channel' => 'email',
            'audience' => 'staff',
            'created_by' => $admin->id,
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.communications.notifications.send', $notification))
            ->assertRedirect();

        Mail::assertSent(AppNotificationMail::class);
    }

    public function test_guardian_consent_mail_can_be_built_for_player(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        $player = Player::query()->with('guardian')->whereHas('guardian', fn ($q) => $q->whereNotNull('email'))->firstOrFail();

        Mail::to($player->guardian->email)->send(new GuardianConsentRequestMail($player));

        Mail::assertSent(GuardianConsentRequestMail::class);
    }
}
