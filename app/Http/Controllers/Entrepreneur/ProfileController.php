<?php

namespace App\Http\Controllers\Entrepreneur;

use App\Http\Controllers\Controller;
use App\Http\Requests\Entrepreneur\UpdateEntrepreneurProfileRequest;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    public function update(UpdateEntrepreneurProfileRequest $request): RedirectResponse
    {
        $request->user()->entrepreneurProfile()->update($request->validated());

        return back()->with('status', 'Profile saved.');
    }
}
