<?php

namespace App\Actions\Mentorship;

use App\Enums\RescheduleStatus;
use App\Models\MeetingReschedule;
use App\Models\User;
use App\Notifications\MeetingRescheduleReviewed;
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
            $locked = MeetingReschedule::query()
                ->whereKey($reschedule->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($locked->status === RescheduleStatus::Pending, 403);

            $locked->update([
                'status' => $accept ? RescheduleStatus::Accepted : RescheduleStatus::Declined,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            if ($accept) {
                $locked->meeting->update([
                    'starts_at' => $locked->new_starts_at,
                    'ends_at' => $locked->new_ends_at,
                ]);
            }
        });

        if ($accept) {
            RescheduleMeeting::announce($reschedule->meeting->refresh());
        }

        $reschedule->requestedBy->notify(new MeetingRescheduleReviewed($reschedule, $accept));
    }
}
