<?php

namespace App\Policies;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
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
