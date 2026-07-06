<?php

namespace App\Http\Controllers\Entrepreneur;

use App\Data\OnboardingProgress;
use App\Http\Controllers\Controller;
use App\Models\MentorPairing;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PairingController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'mentor_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        if (! OnboardingProgress::forUser($user)->isComplete) {
            throw ValidationException::withMessages([
                'mentor_id' => 'Complete your profile before choosing a mentor.',
            ]);
        }

        if ($user->mentorPairing()->exists()) {
            throw ValidationException::withMessages([
                'mentor_id' => 'You have already chosen a mentor.',
            ]);
        }

        $mentor = User::query()
            ->availableMentor()
            ->whereKey($validated['mentor_id'])
            ->first();

        if ($mentor === null) {
            throw ValidationException::withMessages([
                'mentor_id' => 'That mentor is not available.',
            ]);
        }

        MentorPairing::create([
            'entrepreneur_id' => $user->id,
            'mentor_id' => $mentor->id,
        ]);

        return redirect()->route('entrepreneur.dashboard')
            ->with('status', "You're now paired with {$mentor->name}.");
    }
}
