<?php

namespace App\Actions\Mentorship;

use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

class SubmitMeetingReport
{
    public function handle(Meeting $meeting, User $submitter, string $summary): MeetingReport
    {
        try {
            return MeetingReport::create([
                'meeting_id' => $meeting->id,
                'submitted_by_user_id' => $submitter->id,
                'summary' => $summary,
                'submitted_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            abort(403);
        }
    }
}
