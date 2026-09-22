<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\Player;
use App\Support\PlayerCredentials;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PlayerInvitationService
{
    /**
     * Registra la invitación al portal del jugador y prepara el envío de correo.
     *
     * @param  array{user: \App\Models\User, created: bool, password: ?string}|null  $account
     */
    public function record(Player $player, string $email, ?int $invitedBy = null, bool $sendMail = false, ?array $account = null): ?Invitation
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $player->loadMissing(['team.tournament', 'team.category']);

        Invitation::query()
            ->where('player_id', $player->id)
            ->where('kind', 'player')
            ->whereNotIn('family_status', ['completed', 'invalidated'])
            ->update([
                'family_status' => 'invalidated',
                'status' => 'revoked',
            ]);

        $account ??= app(PlayerAccountService::class)->ensureForPlayer($player->fresh(), $invitedBy);

        $invitation = Invitation::create([
            'name' => $player->fullName(),
            'email' => $email,
            'kind' => 'player',
            'family_status' => 'completed',
            'status' => 'accepted',
            'token' => Invitation::makeToken(),
            'scope_type' => Player::class,
            'scope_id' => $player->id,
            'player_id' => $player->id,
            'invited_by' => $invitedBy,
            'accepted_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $payload = $this->mailPayload($player, $invitation, $account);

        if ($sendMail) {
            $this->sendMail($payload);
        } else {
            Log::info('player.portal.invitation.ready', $payload);
        }

        return $invitation;
    }

    /**
     * @param  array{user: \App\Models\User, created: bool, password: ?string}|null  $account
     * @return array<string, mixed>
     */
    public function mailPayload(Player $player, Invitation $invitation, ?array $account): array
    {
        $loginUrl = route('login');
        $accountCreated = (bool) ($account['created'] ?? false);

        return [
            'to' => $invitation->email,
            'subject' => 'Acceso jugador · '.$player->fullName().' · STC Torneos',
            'player_name' => $player->fullName(),
            'team' => $player->team?->name,
            'category' => $player->team?->category?->name,
            'tournament' => $player->team?->tournament?->name,
            'login_url' => $loginUrl,
            'email' => $invitation->email,
            'password' => $accountCreated ? ($account['password'] ?? PlayerCredentials::defaultPassword()) : null,
            'account_created' => $accountCreated,
            'invitation_id' => $invitation->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendMail(array $payload): void
    {
        $body = "Hola {$payload['player_name']},\n\n";
        $body .= "Tu ficha en STC Torneos fue registrada por tu tutor.\n";
        $body .= "Podés ingresar al portal del jugador para ver tu credencial, documentos y el torneo.\n\n";
        $body .= "Ingreso: {$payload['login_url']}\n";
        $body .= "Correo: {$payload['email']}\n";

        if ($payload['account_created'] && $payload['password']) {
            $body .= "Clave inicial: {$payload['password']}\n";
            $body .= "Cambiala en Operación → Mi cuenta después del primer ingreso.\n";
        } else {
            $body .= "Clave: la que ya tenés configurada.\n";
        }

        Mail::raw(
            $body,
            fn ($message) => $message->to($payload['to'])->subject($payload['subject'])
        );
    }
}
