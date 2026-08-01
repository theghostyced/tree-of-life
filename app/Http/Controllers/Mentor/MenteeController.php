<?php

namespace App\Http\Controllers\Mentor;

use App\Enums\MeetingStatus;
use App\Enums\PairingStatus;
use App\Http\Controllers\Controller;
use App\Models\Pairing;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenteeController extends Controller
{
    public function index(Request $request): Response
    {
        $mentor = $request->user();

        $pairings = Pairing::query()
            ->where('mentor_user_id', $mentor->id)
            ->with([
                'entrepreneur:id,name,email,company_id',
                'entrepreneur.company:id,name',
                'entrepreneur.entrepreneurProfile:id,user_id,business_name,sector',
                'conversation:id,pairing_id',
            ])
            ->get();

        [$active, $ended] = $pairings->partition(
            fn (Pairing $pairing) => $pairing->status === PairingStatus::Active
        );

        return Inertia::render('mentor/Mentees', [
            'active' => $active->sortBy(fn (Pairing $p) => $p->entrepreneur->name)
                ->values()->map($this->mapMentee(...))->all(),
            'ended' => $ended->sortByDesc('ended_at')
                ->values()->map($this->mapMentee(...))->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMentee(Pairing $pairing): array
    {
        $entrepreneur = $pairing->entrepreneur;

        $last = $pairing->meetings()
            ->where('status', MeetingStatus::Completed)
            ->latest('ends_at')->first();
        $next = $pairing->meetings()
            ->where('status', MeetingStatus::Confirmed)
            ->where('starts_at', '>', now())
            ->oldest('starts_at')->first();

        return [
            'pairingId' => $pairing->id,
            'userId' => $entrepreneur->id,
            'name' => $entrepreneur->name,
            'email' => $entrepreneur->email,
            'company' => $entrepreneur->company?->name,
            'business' => $entrepreneur->entrepreneurProfile?->business_name,
            'sectors' => $entrepreneur->entrepreneurProfile?->sector ?? [],
            'pairedAt' => $pairing->created_at?->getTimestampMs(),
            'endedAt' => $pairing->ended_at?->getTimestampMs(),
            'lastMeetingAt' => $last?->ends_at->getTimestampMs(),
            'nextMeetingAt' => $next?->starts_at->getTimestampMs(),
            'meetingCount' => $pairing->meetings()->where('status', MeetingStatus::Completed)->count(),
            'conversationId' => $pairing->conversation?->id,
        ];
    }
}
