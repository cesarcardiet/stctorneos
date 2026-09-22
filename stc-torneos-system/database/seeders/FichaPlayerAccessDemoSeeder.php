<?php

namespace Database\Seeders;

use App\Models\Guardian;
use App\Models\Invitation;
use App\Models\Player;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\GuardianAccountService;
use App\Services\GuardianInvitationService;
use App\Services\PlayerAccountService;
use App\Services\PlayerInvitationService;
use App\Support\PlayerCredentials;
use App\Support\TutorCredentials;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos demo para probar la ficha del tutor (9 pasos) y el acceso del jugador.
 *
 * Cuentas:
 * - Tutor ficha pendiente: ficha.tutor@stc.test · clave stctutor
 * - Jugador verificado:     joaquin.jugador@stc.test · clave stcjugador
 */
class FichaPlayerAccessDemoSeeder extends Seeder
{
    public const TUTOR_EMAIL = 'ficha.tutor@stc.test';

    public const PLAYER_EMAIL = 'joaquin.jugador@stc.test';

    public const PENDING_PLAYER_NAME = 'Tomás FichaDemo';

    public const VERIFIED_PLAYER_NAME = 'Joaquín Verificado';

    public function run(): void
    {
        $team = Team::query()->where('name', 'Leones FC')->first()
            ?? Team::query()->whereHas('category')->first();

        if (! $team) {
            $this->command?->warn('FichaPlayerAccessDemoSeeder: no hay equipos. Ejecutá DatabaseSeeder primero.');

            return;
        }

        $delegate = User::query()->where('email', 'delegado@stctorneos.demo')->first();

        $pendingPlayer = $this->seedPendingFicha($team, $delegate?->id);
        $verifiedPlayer = $this->seedVerifiedFicha($team, $delegate?->id);

        $this->command?->info('Ficha demo — jugador pendiente: '.$pendingPlayer->fullName().' (tutor '.self::TUTOR_EMAIL.')');
        $this->command?->info('Ficha demo — jugador verificado: '.$verifiedPlayer->fullName().' ('.self::PLAYER_EMAIL.')');
    }

    private function seedPendingFicha(Team $team, ?int $invitedBy): Player
    {
        $player = Player::updateOrCreate(
            ['team_id' => $team->id, 'first_name' => 'Tomás', 'last_name' => 'FichaDemo'],
            [
                'document_number' => '50999001',
                'birth_date' => '2014-06-15',
                'nationality' => 'Argentina',
                'status' => 'pending',
                'email' => null,
            ]
        );

        Guardian::updateOrCreate(
            ['player_id' => $player->id],
            [
                'name' => 'Carolina FichaDemo',
                'relationship' => 'Madre',
                'email' => self::TUTOR_EMAIL,
                'phone' => '+54 11 5555-9901',
                'consent_status' => 'pending',
            ]
        );

        $player->ensureDocuments();

        Invitation::query()
            ->where('player_id', $player->id)
            ->where('kind', 'guardian')
            ->whereNotIn('family_status', ['completed', 'invalidated'])
            ->update(['family_status' => 'invalidated', 'status' => 'revoked']);

        app(GuardianInvitationService::class)->create(
            $player->fresh('guardian'),
            self::TUTOR_EMAIL,
            $invitedBy,
            sendMail: false
        );

        return $player->fresh(['guardian', 'documents', 'invitations']);
    }

    private function seedVerifiedFicha(Team $team, ?int $invitedBy): Player
    {
        $player = Player::updateOrCreate(
            ['team_id' => $team->id, 'first_name' => 'Joaquín', 'last_name' => 'Verificado'],
            [
                'document_number' => '50999002',
                'birth_date' => '2014-03-20',
                'nationality' => 'Argentina',
                'address' => 'Av. Demo 456, CABA',
                'kit_size' => 'M',
                'jersey_number' => 9,
                'position' => 'Delantero',
                'preferred_foot' => 'Derecha',
                'height' => '1.55',
                'weight' => '46',
                'blood_type' => 'O+',
                'medical_coverage' => 'OSDE',
                'allergies' => 'Ninguna',
                'medication' => 'Ninguna',
                'illnesses' => 'Ninguna',
                'restrictions' => 'Ninguna',
                'emergency_contact' => 'Carolina FichaDemo +54 11 5555-9901',
                'vaccination_calendar_complete' => true,
                'email' => self::PLAYER_EMAIL,
                'status' => 'submitted',
            ]
        );

        Guardian::updateOrCreate(
            ['player_id' => $player->id],
            [
                'name' => 'Carolina FichaDemo',
                'document_number' => '30111222',
                'relationship' => 'Madre',
                'email' => self::TUTOR_EMAIL,
                'phone' => '+54 11 5555-9901',
                'alternate_contact' => 'Pedro FichaDemo · +54 11 5555-9902',
                'consent_status' => 'approved',
            ]
        );

        $player->ensureDocuments();

        foreach (['DNI frente', 'DNI dorso', 'Foto del jugador', 'Autorización', 'Uso de imagen', 'Apto médico'] as $type) {
            $document = $player->documents()->firstOrCreate(['type' => $type], ['status' => 'approved']);
            $document->update([
                'status' => in_array($type, ['Autorización', 'Uso de imagen'], true) ? 'approved' : ($document->status ?: 'pending'),
                'uploaded_by_name' => 'Carolina FichaDemo',
                'uploaded_at' => $document->uploaded_at ?: now(),
            ]);
        }

        Invitation::updateOrCreate(
            [
                'player_id' => $player->id,
                'kind' => 'guardian',
                'email' => self::TUTOR_EMAIL,
            ],
            [
                'name' => 'Carolina FichaDemo',
                'family_status' => 'completed',
                'status' => 'accepted',
                'token' => Invitation::makeToken(),
                'scope_type' => Player::class,
                'scope_id' => $player->id,
                'invited_by' => $invitedBy,
                'accepted_at' => now(),
                'expires_at' => now()->addDays(14),
            ]
        );

        app(GuardianAccountService::class)->ensureForGuardian(
            $player->fresh('guardian'),
            self::TUTOR_EMAIL,
            'Carolina FichaDemo',
            $invitedBy
        );

        $account = app(PlayerAccountService::class)->ensureForPlayer($player->fresh(), $invitedBy);
        app(PlayerInvitationService::class)->record(
            $player->fresh(),
            self::PLAYER_EMAIL,
            $invitedBy,
            sendMail: false,
            account: $account
        );

        $tutor = User::query()->where('email', self::TUTOR_EMAIL)->first();
        if ($tutor && ! Hash::check(TutorCredentials::defaultPassword(), $tutor->password)) {
            $tutor->update(['password' => TutorCredentials::defaultPassword()]);
        }

        $playerUser = User::query()->where('email', self::PLAYER_EMAIL)->first();
        if ($playerUser && ! Hash::check(PlayerCredentials::defaultPassword(), $playerUser->password)) {
            $playerUser->update(['password' => PlayerCredentials::defaultPassword()]);
        }

        return $player->fresh(['guardian', 'documents', 'invitations']);
    }
}
