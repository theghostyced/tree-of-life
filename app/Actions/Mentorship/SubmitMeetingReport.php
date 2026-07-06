<?php

namespace App\Actions\Mentorship;

use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\User;

class SubmitMeetingReport
{
    public function handle(Meeting $meeting, User $submitter, string $summary): MeetingReport
    {
        return MeetingReport::create([
            'meeting_id' => $meeting->id,
            'submitted_by_user_id' => $submitter->id,
            'summary' => $summary,
            'submitted_at' => now(),
        ]);
    }
}
