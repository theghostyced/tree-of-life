<?php

namespace App\Policies;

use App\Enums\MeetingStatus;
use App\Enums\PairingStatus;
use App\Enums\RescheduleStatus;
use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    /**
     * Either party on an active pairing may move a confirmed call that has not
     * started. An entrepreneur may not stack a second proposal on the same
     * meeting while one is still awaiting the mentor.
     */
    public function reschedule(User $user, Meeting $meeting): bool
    {
        $pairing = $meeting->pairing;

        if ($meeting->status !== MeetingStatus::Confirmed || $meeting->starts_at->isPast()) {
            return false;
        }

        if ($pairing->status !== PairingStatus::Active) {
            return false;
        }

        if ($user->id === $pairing->mentor_user_id) {
            return true;
        }

        if ($user->id !== $pairing->entrepreneur_user_id) {
            return false;
        }

        return $meeting->reschedules()
            ->where('status', RescheduleStatus::Pending)
            ->doesntExist();
    }

    /**
     * The pairing's mentor reports on a completed meeting, once.
     */
    public function submitReport(User $user, Meeting $meeting): bool
    {
        return $meeting->status === MeetingStatus::Completed
            && $meeting->pairing->mentor_user_id === $user->id
            && $meeting->report()->doesntExist();
    }
}
