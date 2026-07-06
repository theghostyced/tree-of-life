<?php

namespace App\Http\Controllers\Entrepreneur;

use App\Data\MentorCard;
use App\Data\OnboardingProgress;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MentorController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // The directory is only meaningful once onboarding is done and the
        // entrepreneur has not already chosen a mentor.
        if (! OnboardingProgress::forUser($user)->isComplete || $user->mentorPairing()->exists()) {
            return redirect()->route('entrepreneur.dashboard');
        }

        $mentors = User::query()
            ->availableMentor()
            ->with('mentorProfile')
            ->orderBy('name')
            ->get()
            ->map(fn (User $mentor) => MentorCard::forUser($mentor)->toArray())
            ->values();

        // Focus areas present across the pool — the filter categories.
        $focusAreas = $mentors
            ->flatMap(fn (array $mentor) => $mentor['industries'])
            ->unique()
            ->sort()
            ->values();

        return Inertia::render('entrepreneur/Mentors', [
            'mentors' => $mentors,
            'focusAreas' => $focusAreas,
        ]);
    }
}
