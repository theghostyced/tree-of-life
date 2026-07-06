<?php

namespace App\Actions\Mentorship;

use App\Enums\RescheduleStatus;
use App\Models\MeetingReschedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewMeetingReschedule
{
    /**
     * Accepting applies the proposed times to the meeting; declining leaves
     * the meeting untouched. Both stamp who reviewed and when.
     */
    public function handle(MeetingReschedule $reschedule, User $reviewer, bool $accept): void
    {
        DB::transaction(function () use ($reschedule, $reviewer, $accept) {
            $reschedule->update([
                'status' => $accept ? RescheduleStatus::Accepted : RescheduleStatus::Declined,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            if ($accept) {
                $reschedule->meeting->update([
                    'starts_at' => $reschedule->new_starts_at,
                    'ends_at' => $reschedule->new_ends_at,
                ]);
            }
        });
    }
}
