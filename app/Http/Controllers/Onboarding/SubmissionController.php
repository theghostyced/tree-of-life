<?php

namespace App\Http\Controllers\Onboarding;

use App\Actions\SubmitProfileForReview;
use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Only entrepreneurs and mentors have a reviewable profile.
        abort_unless($user->role->hasProfile(), 403);

        // Submission is only valid from draft (first time) or rejected (resubmit).
        abort_unless(
            in_array($user->account_status, [AccountStatus::Draft, AccountStatus::Rejected], true),
            403,
        );

        app(SubmitProfileForReview::class)->handle($user);

        return redirect($user->role->onboardingPath())
            ->with('status', 'Your profile has been submitted for review.');
    }
}
