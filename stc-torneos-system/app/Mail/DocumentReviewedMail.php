<?php

namespace App\Mail;

use App\Models\PlayerDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentReviewedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PlayerDocument $document)
    {
        $this->document->loadMissing(['player.team.category.tournament', 'player.guardian']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Documentación '.$this->document->statusLabel().' — '.$this->document->player?->fullName(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document-reviewed',
            with: [
                'subjectLine' => 'Revisión documental',
                'heading' => 'Actualización de documentación',
                'document' => $this->document,
                'player' => $this->document->player,
                'actionUrl' => config('app.url'),
                'actionLabel' => 'Ir a STC Torneos',
            ],
        );
    }
}
