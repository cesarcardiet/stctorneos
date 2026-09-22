<?php

namespace App\Mail;

use App\Models\AppNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public AppNotification $notification)
    {
        $this->notification->loadMissing(['tournament']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notification->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.app-notification',
            with: [
                'subjectLine' => $this->notification->title,
                'heading' => $this->notification->title,
                'notification' => $this->notification,
                'actionUrl' => config('app.url'),
                'actionLabel' => 'Abrir STC Torneos',
            ],
        );
    }
}
