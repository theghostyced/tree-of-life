<?php

namespace App\Actions;

use App\Mail\UserInvitationMail;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendInvitationMail
{
    /**
     * Put an invitation email on its way and record whether it got out.
     *
     * The queue broker is a separate service from the mail transport, so it
     * can refuse a connection while mail itself is perfectly healthy. Losing
     * an invitation to that is the worst outcome available: the row is already
     * committed and its raw token exists nowhere else, so the invitee can only
     * be reached by issuing a whole new one. When the broker is unreachable we
     * therefore pay the latency and deliver inline rather than give up.
     *
     * `sendNow` rather than `send`: a Mailable implementing ShouldQueue is
     * re-queued by `send`, which would throw against the same dead broker.
     */
    public function handle(UserInvitation $invitation, string $token): bool
    {
        $mailable = new UserInvitationMail($invitation, $token);

        try {
            Mail::to($invitation->email)->queue($mailable);
        } catch (Throwable $queueFailure) {
            report($queueFailure);

            try {
                Mail::to($invitation->email)->sendNow($mailable);
            } catch (Throwable $mailFailure) {
                report($mailFailure);
                $invitation->forceFill([
                    'delivered_at' => null,
                    'delivery_failed_at' => now(),
                    'delivery_error' => $mailFailure->getMessage(),
                ])->save();

                return false;
            }
        }

        $invitation->forceFill([
            'delivered_at' => now(),
            'delivery_failed_at' => null,
            'delivery_error' => null,
        ])->save();

        return true;
    }
}
