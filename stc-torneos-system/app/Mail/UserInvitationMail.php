<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitación a STC Torneos',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invitation',
            with: [
                'subjectLine' => 'Invitación a STC Torneos',
                'heading' => 'Activá tu acceso',
                'invitation' => $this->invitation,
                'actionUrl' => route('invitations.show', $this->invitation->token),
                'actionLabel' => 'Activar cuenta',
            ],
        );
    }
}
