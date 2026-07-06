<?php

namespace App\Http\Controllers\Mentor;

use App\Actions\Mentorship\SubmitMeetingReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mentor\SubmitMeetingReportRequest;
use App\Models\Meeting;
use Illuminate\Http\RedirectResponse;

class MeetingReportController extends Controller
{
    public function store(SubmitMeetingReportRequest $request, Meeting $meeting): RedirectResponse
    {
        app(SubmitMeetingReport::class)->handle(
            meeting: $meeting,
            submitter: $request->user(),
            summary: $request->validated('summary'),
        );

        return back()->with('status', 'Report submitted.');
    }
}
