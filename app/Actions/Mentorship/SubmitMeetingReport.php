<?php

namespace App\Actions\Mentorship;

use App\Enums\UserRole;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\User;
use App\Notifications\ReportAvailable;
use App\Notifications\ReportSubmitted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;

class SubmitMeetingReport
{
    public function handle(Meeting $meeting, User $submitter, string $summary): MeetingReport
    {
        try {
            $report = MeetingReport::create([
                'meeting_id' => $meeting->id,
                'submitted_by_user_id' => $submitter->id,
                'summary' => $summary,
                'submitted_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            abort(403);
        }

        Notification::send(
            User::query()->where('role', UserRole::Admin)->get(),
            new ReportSubmitted($meeting),
        );
        $meeting->pairing->entrepreneur->notify(new ReportAvailable($meeting));

        return $report;
    }
}
