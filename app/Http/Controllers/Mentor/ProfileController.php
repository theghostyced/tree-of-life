<?php

namespace App\Http\Controllers\Mentor;

use App\Actions\Onboarding\MarkOnboardingComplete;
use App\Data\MentorOnboardingData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mentor\UpdateMentorProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render(
            'mentor/Onboarding',
            MentorOnboardingData::forUser($request->user())->toArray(),
        );
    }

    public function update(UpdateMentorProfileRequest $request): RedirectResponse
    {
        $request->user()->mentorProfile()->update($request->validated());

        app(MarkOnboardingComplete::class)->handle($request->user());

        return back()->with('status', 'Profile saved.');
    }
}
