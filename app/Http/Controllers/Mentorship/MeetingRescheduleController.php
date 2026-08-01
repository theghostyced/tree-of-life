<?php

namespace App\Http\Controllers\Mentorship;

use App\Actions\Mentorship\RescheduleMeeting;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MentorAvailabilitySlot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Shared by both roles: the policy decides who may ask, and the action decides
 * whether the move applies at once or waits for the mentor.
 */
class MeetingRescheduleController extends Controller
{
    public function store(Request $request, Meeting $meeting): RedirectResponse
    {
        Gate::authorize('reschedule', $meeting);

        $data = $request->validate([
            'slot_id' => ['required', 'integer', 'exists:mentor_availability_slots,id'],
            'starts_at' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $isEntrepreneur = $request->user()->id === $meeting->pairing->entrepreneur_user_id;

        if ($isEntrepreneur) {
            $request->validate(['reason' => ['required', 'string', 'max:500']]);
        }

        $reschedule = app(RescheduleMeeting::class)->handle(
            meeting: $meeting,
            actor: $request->user(),
            slot: MentorAvailabilitySlot::findOrFail($data['slot_id']),
            newStart: Carbon::createFromTimestampMs($data['starts_at'])->setTimezone(config('app.timezone')),
            reason: $data['reason'] ?? null,
        );

        return back()->with('status', $reschedule->status->value === 'accepted'
            ? 'Meeting moved.'
            : 'Reschedule requested.');
    }
}
