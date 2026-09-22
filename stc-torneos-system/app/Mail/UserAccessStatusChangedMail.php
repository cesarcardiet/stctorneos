<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserAccessStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Acceso '.$this->user->statusLabel().' — STC Torneos',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-access',
            with: [
                'subjectLine' => 'Estado de acceso',
                'heading' => 'Tu acceso fue actualizado',
                'user' => $this->user,
                'actionUrl' => route('login'),
                'actionLabel' => 'Ir al login',
            ],
        );
    }
}
