<?php

namespace App\Policies;

use App\Enums\MeetingStatus;
use App\Enums\RescheduleStatus;
use App\Models\MeetingReschedule;
use App\Models\User;

class MeetingReschedulePolicy
{
    /**
     * Only the mentor on the meeting's pairing may review, only while the
     * request is pending and the meeting still confirmed, and never their
     * own request.
     */
    public function review(User $user, MeetingReschedule $reschedule): bool
    {
        return $reschedule->status === RescheduleStatus::Pending
            && $reschedule->meeting->status === MeetingStatus::Confirmed
            && $reschedule->meeting->pairing->mentor_user_id === $user->id
            && $reschedule->requested_by_user_id !== $user->id;
    }
}
