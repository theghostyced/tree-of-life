<?php

namespace App\Mail;

use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public UserInvitation $invitation,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You are invited to '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.user-invitation',
            with: [
                'acceptUrl' => url('/invitations/accept/'.$this->token),
                'recipientName' => $this->invitation->name,
                'roleLabel' => $this->invitation->role->label(),
                'appName' => config('app.name'),
            ],
        );
    }
}
