<?php

namespace App\Http\Controllers\Mentor;

use App\Enums\MeetingStatus;
use App\Enums\PairingStatus;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Pairing;
use App\Support\AvailabilityOptions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    public function index(Request $request): Response
    {
        $mentor = $request->user();

        $meetings = Meeting::query()
            ->whereHas('pairing', fn ($p) => $p->where('mentor_user_id', $mentor->id))
            ->with(['pairing.entrepreneur:id,name', 'report:id,meeting_id,summary'])
            ->orderBy('starts_at')
            ->get();

        [$upcoming, $past] = $meetings->partition(
            fn (Meeting $m) => $m->status === MeetingStatus::Confirmed && $m->starts_at->isFuture()
        );

        $availability = Pairing::query()
            ->where('mentor_user_id', $mentor->id)
            ->where('status', PairingStatus::Active)
            ->get()
            ->mapWithKeys(fn (Pairing $pairing) => [
                $pairing->id => AvailabilityOptions::forPairing($pairing),
            ])->all();

        return Inertia::render('mentor/Meetings', [
            'upcoming' => $upcoming->values()->map($this->mapMeeting(...))->all(),
            'past' => $past->sortByDesc('starts_at')->values()->map($this->mapMeeting(...))->all(),
            'availability' => $availability,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMeeting(Meeting $meeting): array
    {
        return [
            'id' => $meeting->id,
            'pairingId' => $meeting->pairing_id,
            'counterpartName' => $meeting->pairing->entrepreneur->name,
            'startsAt' => $meeting->starts_at->getTimestampMs(),
            'endsAt' => $meeting->ends_at->getTimestampMs(),
            'sessionType' => $meeting->session_type,
            'location' => $meeting->location,
            'meetingLink' => $meeting->meeting_link,
            'agenda' => $meeting->agenda,
            'status' => $meeting->status->value,
            'reportSummary' => $meeting->report?->summary,
            'canReport' => $meeting->status === MeetingStatus::Completed
                && $meeting->report === null,
        ];
    }
}
