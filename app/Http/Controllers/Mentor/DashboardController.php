<?php

namespace App\Http\Controllers\Mentor;

use App\Data\OnboardingProgress;
use App\Enums\MeetingStatus;
use App\Enums\PairingStatus;
use App\Enums\RescheduleStatus;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingReschedule;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $mentor = $request->user();

        return Inertia::render('mentor/Dashboard', [
            'onboarding' => OnboardingProgress::forUser($mentor)->toArray(),
            'attention' => [
                'reschedules' => $this->pendingReschedules($mentor),
                'missingReports' => $this->missingReports($mentor),
            ],
            'meetings' => $this->weekMeetings($mentor),
            'mentees' => $this->mentees($mentor),
            'availability' => $this->availability($mentor),
            'stats' => $this->stats($mentor),
        ]);
    }

    /**
     * Pending requests on this mentor's meetings that someone else raised.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pendingReschedules(User $mentor): array
    {
        return MeetingReschedule::query()
            ->where('status', RescheduleStatus::Pending)
            ->whereNot('requested_by_user_id', $mentor->id)
            ->whereHas('meeting', fn ($q) => $q
                ->where('status', MeetingStatus::Confirmed)
                ->whereHas('pairing', fn ($p) => $p->where('mentor_user_id', $mentor->id)))
            ->with('meeting.pairing.entrepreneur:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (MeetingReschedule $r): array => [
                'id' => $r->id,
                'menteeName' => $r->meeting->pairing->entrepreneur->name,
                'previousStartsAt' => $r->previous_starts_at->getTimestampMs(),
                'newStartsAt' => $r->new_starts_at->getTimestampMs(),
                'newEndsAt' => $r->new_ends_at->getTimestampMs(),
                'reason' => $r->reason,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function missingReports(User $mentor): array
    {
        return Meeting::query()
            ->where('status', MeetingStatus::Completed)
            ->whereDoesntHave('report')
            ->whereHas('pairing', fn ($p) => $p->where('mentor_user_id', $mentor->id))
            ->with('pairing.entrepreneur:id,name')
            ->orderByDesc('ends_at')
            ->get()
            ->map(fn (Meeting $m): array => [
                'meetingId' => $m->id,
                'menteeName' => $m->pairing->entrepreneur->name,
                'endedAt' => $m->ends_at->getTimestampMs(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function weekMeetings(User $mentor): array
    {
        return Meeting::query()
            ->where('status', MeetingStatus::Confirmed)
            ->whereBetween('starts_at', [now(), now()->addDays(7)])
            ->whereHas('pairing', fn ($p) => $p->where('mentor_user_id', $mentor->id))
            ->with('pairing.entrepreneur:id,name')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Meeting $m): array => [
                'id' => $m->id,
                'menteeName' => $m->pairing->entrepreneur->name,
                'startsAt' => $m->starts_at->getTimestampMs(),
                'endsAt' => $m->ends_at->getTimestampMs(),
                'sessionType' => $m->session_type,
                'location' => $m->location,
                'meetingLink' => $m->meeting_link,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mentees(User $mentor): array
    {
        return Pairing::query()
            ->where('mentor_user_id', $mentor->id)
            ->where('status', PairingStatus::Active)
            ->with(['entrepreneur:id,name,company_id', 'entrepreneur.company:id,name'])
            ->get()
            ->map(function (Pairing $pairing): array {
                $last = $pairing->meetings()
                    ->where('status', MeetingStatus::Completed)
                    ->latest('ends_at')->first();
                $next = $pairing->meetings()
                    ->where('status', MeetingStatus::Confirmed)
                    ->where('starts_at', '>', now())
                    ->oldest('starts_at')->first();

                return [
                    'pairingId' => $pairing->id,
                    'name' => $pairing->entrepreneur->name,
                    'company' => $pairing->entrepreneur->company?->name,
                    'lastMeetingAt' => $last?->ends_at->getTimestampMs(),
                    'nextMeetingAt' => $next?->starts_at->getTimestampMs(),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function availability(User $mentor): array
    {
        $slots = MentorAvailabilitySlot::query()
            ->where('mentor_user_id', $mentor->id)
            ->where('is_active', true)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return [
            'activeCount' => $slots->count(),
            'slots' => $slots->map(fn (MentorAvailabilitySlot $s): array => [
                'id' => $s->id,
                'dayOfWeek' => $s->day_of_week,
                'startTime' => substr((string) $s->start_time, 0, 5),
                'endTime' => substr((string) $s->end_time, 0, 5),
                'sessionType' => $s->session_type,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(User $mentor): array
    {
        $completed = Meeting::query()
            ->where('status', MeetingStatus::Completed)
            ->whereHas('pairing', fn ($p) => $p->where('mentor_user_id', $mentor->id))
            ->get();

        $minutes = $completed->sum(fn (Meeting $m) => $m->durationMinutes());

        return [
            'menteeCount' => Pairing::query()
                ->where('mentor_user_id', $mentor->id)
                ->where('status', PairingStatus::Active)
                ->count(),
            'completedCount' => $completed->count(),
            'hoursMentored' => round($minutes / 60 * 2) / 2,
        ];
    }
}
