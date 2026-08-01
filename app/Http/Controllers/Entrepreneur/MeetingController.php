<?php

namespace App\Http\Controllers\Entrepreneur;

use App\Actions\Mentorship\BookMeeting;
use App\Enums\MeetingStatus;
use App\Enums\PairingStatus;
use App\Enums\RescheduleStatus;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;
use App\Support\AvailabilityOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        ['mentors' => $mentors, 'availability' => $availability] = $this->bookingData($user);

        return Inertia::render('entrepreneur/Meetings', [
            'upcoming' => $upcoming->values()->map($this->mapMeeting(...))->all(),
            'past' => $past->sortByDesc('starts_at')->values()->map($this->mapMeeting(...))->all(),
            'mentors' => $mentors,
            'availability' => $availability,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slot_id' => ['required', 'integer', 'exists:mentor_availability_slots,id'],
            'starts_at' => ['nullable', 'integer'],
        ]);

        $slot = MentorAvailabilitySlot::findOrFail($data['slot_id']);

        $pairing = Pairing::query()
            ->where('entrepreneur_user_id', $request->user()->id)
            ->where('mentor_user_id', $slot->mentor_user_id)
            ->where('status', PairingStatus::Active)
            ->first();

        abort_if($pairing === null || ! $slot->is_active, 403);

        $startsAt = isset($data['starts_at'])
            ? Carbon::createFromTimestampMs($data['starts_at'])->setTimezone(config('app.timezone'))
            : null;

        app(BookMeeting::class)->handle($pairing, $slot, $startsAt);

        return back()->with('status', 'Meeting booked.');
    }

    /**
     * The entrepreneur's active-pairing mentors, plus every free upcoming slot
     * occurrence for each pairing (keyed by pairing id) for the week calendar.
     *
     * @return array{mentors: list<array<string, mixed>>, availability: array<int, list<array<string, mixed>>>}
     */
    private function bookingData($user): array
    {
        $pairings = Pairing::query()
            ->where('entrepreneur_user_id', $user->id)
            ->where('status', PairingStatus::Active)
            ->with('mentor:id,name')
            ->get();

        $mentors = [];
        $availability = [];

        foreach ($pairings as $pairing) {
            $mentors[] = [
                'pairingId' => $pairing->id,
                'mentorId' => $pairing->mentor_user_id,
                'name' => $pairing->mentor->name,
            ];

            $availability[$pairing->id] = AvailabilityOptions::forPairing($pairing);
        }

        return ['mentors' => $mentors, 'availability' => $availability];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMeeting(Meeting $meeting): array
    {
        return [
            'id' => $meeting->id,
            'pairingId' => $meeting->pairing_id,
            'reschedulePending' => $meeting->reschedules()
                ->where('status', RescheduleStatus::Pending)->exists(),
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
