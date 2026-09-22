<?php

namespace App\Mail;

use App\Models\Player;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class GuardianConsentRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Player $player)
    {
        $this->player->loadMissing(['team.category.tournament', 'guardian']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Autorización de ficha — '.$this->player->fullName(),
        );
    }

    public function content(): Content
    {
        $consentUrl = URL::temporarySignedRoute(
            'consent.show',
            now()->addDays(7),
            ['player' => $this->player->id]
        );

        return new Content(
            view: 'emails.guardian-consent',
            with: [
                'subjectLine' => 'Autorización de ficha',
                'heading' => 'Revisión de ficha del jugador',
                'player' => $this->player,
                'actionUrl' => $consentUrl,
                'actionLabel' => 'Revisar y autorizar',
            ],
        );
    }
}
