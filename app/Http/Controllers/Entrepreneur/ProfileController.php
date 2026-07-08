<?php

namespace App\Http\Controllers\Entrepreneur;

use App\Actions\Onboarding\MarkOnboardingComplete;
use App\Data\EntrepreneurOnboardingData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Entrepreneur\UpdateEntrepreneurProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render(
            'entrepreneur/Onboarding',
            EntrepreneurOnboardingData::forUser($request->user())->toArray(),
        );
    }

    public function update(UpdateEntrepreneurProfileRequest $request): RedirectResponse
    {
        $request->user()->entrepreneurProfile()->update($request->validated());

        app(MarkOnboardingComplete::class)->handle($request->user());

        return back()->with('status', 'Profile saved.');
    }
}
