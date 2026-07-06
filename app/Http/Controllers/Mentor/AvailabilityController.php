<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mentor\StoreAvailabilitySlotRequest;
use App\Models\MentorAvailabilitySlot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AvailabilityController extends Controller
{
    public function index(Request $request): Response
    {
        $slots = MentorAvailabilitySlot::query()
            ->where('mentor_user_id', $request->user()->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(fn (MentorAvailabilitySlot $slot): array => [
                'id' => $slot->id,
                'dayOfWeek' => $slot->day_of_week,
                'startTime' => substr((string) $slot->start_time, 0, 5),
                'endTime' => substr((string) $slot->end_time, 0, 5),
                'sessionType' => $slot->session_type,
                'location' => $slot->location,
                'meetingLink' => $slot->meeting_link,
            ])
            ->all();

        return Inertia::render('mentor/Availability', ['slots' => $slots]);
    }

    public function store(StoreAvailabilitySlotRequest $request): RedirectResponse
    {
        $request->user()->availabilitySlots()->create([
            ...$request->validated(),
            'timezone' => config('app.timezone'),
            'is_active' => true,
        ]);

        return back()->with('status', 'Availability added.');
    }

    public function destroy(Request $request, MentorAvailabilitySlot $slot): RedirectResponse
    {
        abort_unless($slot->mentor_user_id === $request->user()->id, 403);

        $slot->delete();

        return back()->with('status', 'Availability removed.');
    }
}
