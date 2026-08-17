<?php

namespace App\Mail;

use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Throwable;

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

    /**
     * Laravel calls this when the queued send exhausts its attempts.
     *
     * Without it a rejection by the mail provider is invisible: the queue push
     * succeeded, so the request returned success, and the actual send happens
     * later inside the worker where nothing is watching. That is the difference
     * between "no error and no email" and a row that says why.
     */
    public function failed(Throwable $e): void
    {
        $this->invitation->forceFill([
            'delivered_at' => null,
            'delivery_failed_at' => now(),
            'delivery_error' => $e->getMessage(),
        ])->save();
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
