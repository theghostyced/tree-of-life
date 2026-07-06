<?php

namespace App\Http\Controllers\Entrepreneur;

use App\Actions\Mentorship\BookMeeting;
use App\Enums\MeetingStatus;
use App\Enums\PairingStatus;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $meetings = Meeting::query()
            ->whereHas('pairing', fn ($p) => $p->where('entrepreneur_user_id', $user->id))
            ->with(['pairing.mentor:id,name', 'report:id,meeting_id,summary'])
            ->orderBy('starts_at')
            ->get();

        [$upcoming, $past] = $meetings->partition(
            fn (Meeting $m) => $m->status === MeetingStatus::Confirmed && $m->starts_at->isFuture()
        );

        return Inertia::render('entrepreneur/Meetings', [
            'upcoming' => $upcoming->values()->map($this->mapMeeting(...))->all(),
            'past' => $past->sortByDesc('starts_at')->values()->map($this->mapMeeting(...))->all(),
            'bookable' => $this->bookable($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slot_id' => ['required', 'integer', 'exists:mentor_availability_slots,id'],
        ]);

        $slot = MentorAvailabilitySlot::findOrFail($data['slot_id']);

        $pairing = Pairing::query()
            ->where('entrepreneur_user_id', $request->user()->id)
            ->where('mentor_user_id', $slot->mentor_user_id)
            ->where('status', PairingStatus::Active)
            ->first();

        abort_if($pairing === null || ! $slot->is_active, 403);

        app(BookMeeting::class)->handle($pairing, $slot);

        return back()->with('status', 'Meeting booked.');
    }

    /**
     * The next free occurrence of each active slot for each of the
     * entrepreneur's mentors.
     *
     * @return array<int, array<string, mixed>>
     */
    private function bookable($user): array
    {
        $pairings = Pairing::query()
            ->where('entrepreneur_user_id', $user->id)
            ->where('status', PairingStatus::Active)
            ->with('mentor:id,name')
            ->get();

        $options = [];
        foreach ($pairings as $pairing) {
            $slots = MentorAvailabilitySlot::query()
                ->where('mentor_user_id', $pairing->mentor_user_id)
                ->where('is_active', true)
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get();

            foreach ($slots as $slot) {
                $next = BookMeeting::nextFreeOccurrence($slot, $pairing);
                if ($next === null) {
                    continue;
                }
                $options[] = [
                    'slotId' => $slot->id,
                    'mentorName' => $pairing->mentor->name,
                    'startsAt' => $next->getTimestampMs(),
                    'sessionType' => $slot->session_type,
                ];
            }
        }

        usort($options, fn ($a, $b) => $a['startsAt'] <=> $b['startsAt']);

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMeeting(Meeting $meeting): array
    {
        return [
            'id' => $meeting->id,
            'counterpartName' => $meeting->pairing->mentor->name,
            'startsAt' => $meeting->starts_at->getTimestampMs(),
            'endsAt' => $meeting->ends_at->getTimestampMs(),
            'sessionType' => $meeting->session_type,
            'location' => $meeting->location,
            'meetingLink' => $meeting->meeting_link,
            'agenda' => $meeting->agenda,
            'status' => $meeting->status->value,
            'reportSummary' => $meeting->report?->summary,
        ];
    }
}
