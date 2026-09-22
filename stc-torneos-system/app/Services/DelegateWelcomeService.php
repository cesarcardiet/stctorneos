<?php

namespace App\Services;

use App\Mail\DelegateWelcomeMail;
use App\Models\Delegation;
use App\Models\Tournament;
use App\Models\User;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\Mail;

class DelegateWelcomeService
{
    public function send(
        User $user,
        Tournament $tournament,
        Delegation $club,
        bool $isNewAccount,
        ?string $plainPassword = null,
    ): array {
        $loginUrl = route('login', absolute: true);
        $password = $isNewAccount ? $plainPassword : null;
        $message = $this->message($user, $tournament, $club, $loginUrl, $password);

        Mail::to($user->email)->send(new DelegateWelcomeMail(
            $user,
            $club,
            $tournament,
            $loginUrl,
            $password,
            $message,
        ));

        return [
            'message' => $message,
            'whatsapp_url' => WhatsApp::url($user->phone, $message),
        ];
    }

    public function message(
        User $user,
        Tournament $tournament,
        Delegation $club,
        ?string $loginUrl = null,
        ?string $password = null,
    ): string {
        $loginUrl ??= route('login', absolute: true);
        $lines = [
            'Hola '.$user->name.',',
            '',
            'Quedaste asignado/a como delegado/a de '.$club->name.' en '.$tournament->name.' (STC Torneos).',
            '',
            'Datos para ingresar a tu panel:',
            'Correo: '.$user->email,
        ];

        if ($password) {
            $lines[] = 'Clave: '.$password;
        } else {
            $lines[] = 'Clave: la que ya tenés configurada (si no la recordás, pedila al administrador).';
        }

        $lines[] = 'Ingreso: '.$loginUrl;
        $lines[] = '';
        $lines[] = 'Con este acceso solo vas a ver '.$club->name.'. Podés cambiar la clave desde tu panel cuando quieras.';
        $lines[] = '';
        $lines[] = 'Saludos,';
        $lines[] = 'STC Torneos';

        return implode("\n", $lines);
    }

    /**
     * Link de reenvío desde el listado: no incluye clave (ya está hasheada / puede haber cambiado).
     */
    public function whatsappUrl(User $user, Tournament $tournament, Delegation $club): ?string
    {
        return WhatsApp::url(
            $user->phone,
            $this->message($user, $tournament, $club, route('login', absolute: true), null)
        );
    }
}
