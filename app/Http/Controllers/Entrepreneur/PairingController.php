<?php

namespace App\Http\Controllers\Entrepreneur;

use App\Data\OnboardingProgress;
use App\Http\Controllers\Controller;
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

        $mentor = User::query()
            ->availableMentor()
            ->whereKey($validated['mentor_id'])
            ->first();

        if ($mentor === null) {
            throw ValidationException::withMessages([
                'mentor_id' => 'That mentor is not available.',
            ]);
        }

        if ($user->mentors()->whereKey($mentor->id)->exists()) {
            throw ValidationException::withMessages([
                'mentor_id' => "You're already working with {$mentor->name}.",
            ]);
        }

        $user->mentors()->attach($mentor->id);

        return redirect()->route('entrepreneur.mentors.index')
            ->with('status', "You're now working with {$mentor->name}.");
    }
}
