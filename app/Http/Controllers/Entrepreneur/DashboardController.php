<?php

namespace App\Http\Controllers\Entrepreneur;

use App\Data\MentorCard;
use App\Data\OnboardingProgress;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $pairing = $user->mentorPairing()->with('mentor.mentorProfile')->first();

        return Inertia::render('entrepreneur/Dashboard', [
            'onboarding' => OnboardingProgress::forUser($user)->toArray(),
            'mentor' => $pairing ? MentorCard::forUser($pairing->mentor)->toArray() : null,
        ]);
    }
}
