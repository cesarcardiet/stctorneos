<?php

namespace App\Mail;

use App\Models\Player;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlayerStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Player $player, public ?string $note = null)
    {
        $this->player->loadMissing(['team.category.tournament', 'guardian']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ficha '.$this->player->statusLabel().' — '.$this->player->fullName(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.player-status',
            with: [
                'subjectLine' => 'Estado de ficha',
                'heading' => 'Actualización de la ficha',
                'player' => $this->player,
                'note' => $this->note,
                'actionUrl' => config('app.url'),
                'actionLabel' => 'Ir a STC Torneos',
            ],
        );
    }
}
