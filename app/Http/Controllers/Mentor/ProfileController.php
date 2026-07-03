<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mentor\UpdateMentorProfileRequest;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    public function update(UpdateMentorProfileRequest $request): RedirectResponse
    {
        $request->user()->mentorProfile()->update($request->validated());

        return back()->with('status', 'Profile saved.');
    }
}
