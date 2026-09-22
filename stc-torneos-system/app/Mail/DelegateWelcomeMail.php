<?php

namespace App\Mail;

use App\Models\Delegation;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DelegateWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Delegation $club,
        public Tournament $tournament,
        public string $loginUrl,
        public ?string $password,
        public string $messageText,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Acceso delegado · '.$this->club->name.' · STC Torneos',
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.delegate-welcome',
            text: 'mail.delegate-welcome-text',
        );
    }
}
