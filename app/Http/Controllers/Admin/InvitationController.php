<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateUserInvitation;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvitationRequest;
use App\Mail\UserInvitationMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class InvitationController extends Controller
{
    public function store(StoreInvitationRequest $request): RedirectResponse
    {
        [$invitation, $token] = app(CreateUserInvitation::class)->handle(
            email: $request->validated('email'),
            role: UserRole::from($request->validated('role')),
            invitedBy: $request->user(),
            name: $request->validated('name'),
        );

        Mail::to($invitation->email)->queue(new UserInvitationMail($invitation, $token));

        return redirect()
            ->route('admin.invitations.index')
            ->with('status', 'Invitation sent to '.$invitation->email.'.');
    }
}
