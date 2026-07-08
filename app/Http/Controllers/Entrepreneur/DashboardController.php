<?php

namespace App\Http\Controllers\Entrepreneur;

use App\Data\MentorCard;
use App\Data\OnboardingProgress;
use App\Enums\MeetingStatus;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $mentors = $user->mentors()
            ->with('mentorProfile')
            ->orderBy('name')
            ->get()
            ->map(fn (User $mentor) => MentorCard::forUser($mentor)->toArray())
            ->values();

        return Inertia::render('entrepreneur/Dashboard', [
            'onboarding' => OnboardingProgress::forUser($user)->toArray(),
            'mentors' => $mentors,
            'sessions' => $this->sessionsByMonth($user),
        ]);
    }

    /**
     * The entrepreneur's meetings per month over the last 6 months — an
     * aggregate count per month (no meeting rows loaded into PHP).
     *
     * @return array<int, array<string, mixed>>
     */
    private function sessionsByMonth(User $user): array
    {
        return collect(range(5, 0))->map(function (int $back) use ($user): array {
            $month = now()->subMonths($back);

            $count = Meeting::query()
                ->whereNot('status', MeetingStatus::Cancelled)
                ->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->whereHas('pairing', fn ($p) => $p->where('entrepreneur_user_id', $user->id))
                ->count();

            return ['month' => $month->format('M'), 'sessions' => $count];
        })->values()->all();
    }
}
