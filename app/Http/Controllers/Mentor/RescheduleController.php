<?php

namespace App\Http\Controllers\Mentor;

use App\Actions\Mentorship\ReviewMeetingReschedule;
use App\Http\Controllers\Controller;
use App\Models\MeetingReschedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RescheduleController extends Controller
{
    public function accept(Request $request, MeetingReschedule $reschedule): RedirectResponse
    {
        Gate::authorize('review', $reschedule);

        app(ReviewMeetingReschedule::class)->handle($reschedule, $request->user(), accept: true);

        return back()->with('status', 'Meeting moved to the new time.');
    }

    public function decline(Request $request, MeetingReschedule $reschedule): RedirectResponse
    {
        Gate::authorize('review', $reschedule);

        app(ReviewMeetingReschedule::class)->handle($reschedule, $request->user(), accept: false);

        return back()->with('status', 'Reschedule request declined.');
    }
}
