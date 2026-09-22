<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Invitation;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class GuardianInvitationService
{
    /**
     * @param  bool|null  $mailSent  true si se envió, false si falló, null si no se intentó
     */
    public function create(Player $player, string $email, ?int $invitedBy = null, bool $sendMail = true, ?bool &$mailSent = null): Invitation
    {
        $player->loadMissing('guardian');
        $email = strtolower(trim($email));
        $mailSent = null;

        Invitation::query()
            ->where('player_id', $player->id)
            ->where('kind', 'guardian')
            ->whereNotIn('family_status', ['completed', 'invalidated'])
            ->update([
                'family_status' => 'invalidated',
                'status' => 'revoked',
            ]);

        $invitation = Invitation::create([
            'name' => $player->guardian?->name ?: 'Tutor de '.$player->fullName(),
            'email' => $email,
            'kind' => 'guardian',
            'family_status' => 'generated',
            'status' => 'pending',
            'token' => Invitation::makeToken(),
            'scope_type' => Player::class,
            'scope_id' => $player->id,
            'player_id' => $player->id,
            'invited_by' => $invitedBy,
            'expires_at' => now()->addDays(14),
        ]);

        if (in_array($player->status, ['draft', 'pending'], true)) {
            $player->update(['status' => 'awaiting_guardian']);
        }

        // Si el correo ya es admin/delegado/etc., igual generamos el link de ficha y lo enviamos.
        // Solo no creamos acceso "tutor" al panel con esa misma cuenta.
        $account = app(GuardianAccountService::class)->ensureForGuardian(
            $player->fresh('guardian'),
            $email,
            $invitation->name,
            $invitedBy
        );

        $loginUrl = route('login');
        $mailBody = "Hola {$invitation->name},\n\n";
        $mailBody .= "Confirmá la autorización legal de {$player->fullName()} en este enlace:\n{$invitation->publicUrl()}\n\n";
        $mailBody .= "Son las aceptaciones de participación, uso de imagen y aptitud médica. Vence el {$invitation->expires_at->format('d/m/Y')}.\n\n";

        if ($account) {
            $mailBody .= "También podés ingresar al panel con:\nCorreo: {$account['user']->email}\n";
            if ($account['created'] && $account['password']) {
                $mailBody .= "Clave inicial: {$account['password']}\n";
                $mailBody .= "Ingreso: {$loginUrl}\n";
                $mailBody .= "Podés cambiar la clave en Operación → Mi cuenta.\n";
            } else {
                $mailBody .= "Clave: la que ya tenés configurada.\nIngreso: {$loginUrl}\n";
            }
        }

        if ($sendMail) {
            try {
                Mail::raw(
                    $mailBody,
                    fn ($message) => $message->to($invitation->email)->subject('Confirmación del tutor · '.$player->fullName().' · STC Torneos')
                );
                $mailSent = true;
            } catch (\Throwable $exception) {
                $mailSent = false;
                report($exception);
            }
        }

        return $invitation;
    }

    public function ensureInvitation(Player $player, string $email, ?int $invitedBy = null, bool $sendMail = false): Invitation
    {
        $player->loadMissing('guardian');

        $existing = Invitation::query()
            ->where('player_id', $player->id)
            ->where('kind', 'guardian')
            ->orderByDesc('id')
            ->get()
            ->first(fn (Invitation $invitation) => $invitation->isUsable() || $invitation->canViewFicha());

        if ($existing) {
            return $existing;
        }

        return $this->create($player, $email, $invitedBy, $sendMail);
    }

    public function invalidate(Player $player): void
    {
        $invitation = $player->latestGuardianInvitation();
        abort_unless($invitation && $invitation->isUsable(), 422, 'No hay un enlace vigente para invalidar.');

        $invitation->update([
            'family_status' => 'invalidated',
            'status' => 'revoked',
        ]);
    }

    public function ensureGuardianFromPlayerForm(Player $player, array $data): ?Guardian
    {
        $name = trim((string) ($data['guardian_name'] ?? $player->guardian?->name ?? ''));
        $email = trim((string) ($data['guardian_email'] ?? $player->guardian?->email ?? ''));

        if ($name === '' && $email === '') {
            return $player->guardian;
        }

        $attributes = [
            'name' => $name !== '' ? $name : 'Tutor de '.$player->fullName(),
            'document_number' => $data['guardian_document_number'] ?? $player->guardian?->document_number,
            'phone' => $data['guardian_phone'] ?? $player->guardian?->phone,
            'email' => $email !== '' ? $email : $player->guardian?->email,
            'alternate_contact' => $data['guardian_alternate_contact'] ?? $player->guardian?->alternate_contact,
            'consent_status' => $data['consent_status'] ?? $player->guardian?->consent_status ?? 'pending',
        ];

        $relationship = trim((string) ($data['guardian_relationship'] ?? $player->guardian?->relationship ?? ''));
        if ($relationship !== '') {
            $attributes['relationship'] = $relationship;
        }

        $guardian = Guardian::updateOrCreate(
            ['player_id' => $player->id],
            $attributes
        );

        $email = trim((string) ($guardian->email ?? ''));
        if ($email !== '') {
            app(GuardianAccountService::class)->ensureForGuardian(
                $player->fresh(['guardian', 'team.tournament']),
                $email,
                $guardian->name
            );
        }

        return $guardian;
    }
}
