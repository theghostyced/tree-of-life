<?php

namespace App\Actions\Mentorship;

use App\Actions\Chat\PostSystemMessage;
use App\Enums\RescheduleStatus;
use App\Models\Meeting;
use App\Models\MeetingReschedule;
use App\Models\MentorAvailabilitySlot;
use App\Models\User;
use App\Notifications\MeetingRescheduled;
use App\Notifications\MeetingRescheduleRequested;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RescheduleMeeting
{
    /**
     * Move a meeting, or propose moving it, depending on who is asking.
     *
     * The mentor owns the availability, so their move applies at once. An
     * entrepreneur's is a proposal the mentor reviews, and the meeting keeps
     * its time until then. Either way the proposed time must be a free
     * occurrence of one of that mentor's published slots.
     */
    public function handle(
        Meeting $meeting,
        User $actor,
        MentorAvailabilitySlot $slot,
        CarbonInterface $newStart,
        ?string $reason = null,
    ): MeetingReschedule {
        $pairing = $meeting->pairing;

        abort_unless($slot->mentor_user_id === $pairing->mentor_user_id, 403);
        abort_unless($slot->is_active, 422, 'That slot is no longer available.');

        $starts = BookMeeting::freeOccurrences($slot, $pairing)
            ->first(fn (CarbonInterface $occurrence) => $occurrence->equalTo($newStart));

        abort_if($starts === null, 422, 'That time is no longer available.');

        $ends = $starts->copy()->addMinutes(BookMeeting::durationMinutes($slot));
        $byMentor = $actor->id === $pairing->mentor_user_id;

        $reschedule = DB::transaction(function () use ($meeting, $actor, $slot, $starts, $ends, $reason, $byMentor) {
            $reschedule = MeetingReschedule::create([
                'meeting_id' => $meeting->id,
                'requested_by_user_id' => $actor->id,
                'status' => $byMentor ? RescheduleStatus::Accepted : RescheduleStatus::Pending,
                'reason' => $reason,
                'previous_starts_at' => $meeting->starts_at,
                'previous_ends_at' => $meeting->ends_at,
                'new_starts_at' => $starts,
                'new_ends_at' => $ends,
                'reviewed_by_user_id' => $byMentor ? $actor->id : null,
                'reviewed_at' => $byMentor ? now() : null,
            ]);

            if ($byMentor) {
                $meeting->update([
                    'mentor_availability_slot_id' => $slot->id,
                    'starts_at' => $starts,
                    'ends_at' => $ends,
                ]);
            }

            return $reschedule;
        });

        if ($byMentor) {
            self::announce($meeting->refresh());
            $pairing->entrepreneur->notify(new MeetingRescheduled($reschedule));
        } else {
            $pairing->mentor->notify(new MeetingRescheduleRequested($reschedule));
        }

        return $reschedule;
    }

    /**
     * Announce a moved call in the pairing's chat. Defensive, as booking is, so
     * a legacy pairing without a conversation never fails a reschedule.
     */
    public static function announce(Meeting $meeting): void
    {
        $conversation = $meeting->pairing->conversation()->first();

        if ($conversation === null) {
            return;
        }

        try {
            $when = $meeting->starts_at->timezone($meeting->timezone)->format('D j M, g:i A');
            app(PostSystemMessage::class)->handle($conversation, "🔁 Call moved to {$when}");
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
