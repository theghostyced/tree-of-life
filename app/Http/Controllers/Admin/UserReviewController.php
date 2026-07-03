<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApproveUser;
use App\Actions\RejectUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class UserReviewController extends Controller
{
    public function approve(User $user): RedirectResponse
    {
        Gate::authorize('review-accounts');

        app(ApproveUser::class)->handle($user);

        return back()->with('status', $user->name.' has been approved.');
    }

    public function reject(RejectUserRequest $request, User $user): RedirectResponse
    {
        app(RejectUser::class)->handle($user, $request->validated('reason'));

        return back()->with('status', $user->name.' has been rejected.');
    }
}
