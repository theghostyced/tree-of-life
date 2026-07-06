<?php

namespace App\Http\Controllers\Entrepreneur;

use App\Data\MentorCard;
use App\Data\OnboardingProgress;
use App\Http\Controllers\Controller;
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
        ]);
    }
}
