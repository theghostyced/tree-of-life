<?php

namespace App\Http\Controllers;

use App\Actions\UpdateUserProfile;
use App\Data\ProfileData;
use App\Http\Requests\UpdateProfileRequest;
use App\Support\RoleBio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('shared/Profile', [
            'profile' => ProfileData::forUser($request->user())->toArray(),
            'bio' => RoleBio::forUser($request->user()),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        app(UpdateUserProfile::class)->handle(
            $request->user(),
            $request->validated('name'),
            $request->validated('bio'),
            $request->file('avatar'),
            $request->boolean('remove_avatar'),
        );

        return redirect()->route('profile.edit')->with('success', 'Profile saved.');
    }
}
