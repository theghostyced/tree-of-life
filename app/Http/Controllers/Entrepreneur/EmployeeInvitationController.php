<?php

namespace App\Http\Controllers\Entrepreneur;

use App\Actions\CreateUserInvitation;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Entrepreneur\StoreEmployeeInvitationRequest;
use App\Mail\UserInvitationMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class EmployeeInvitationController extends Controller
{
    public function store(StoreEmployeeInvitationRequest $request): RedirectResponse
    {
        $entrepreneur = $request->user();

        [$invitation, $token] = app(CreateUserInvitation::class)->handle(
            email: $request->validated('email'),
            role: UserRole::Employee,
            invitedBy: $entrepreneur,
            companyId: $entrepreneur->ownedCompany?->id,
            name: $request->validated('name'),
        );

        Mail::to($invitation->email)->queue(new UserInvitationMail($invitation, $token));

        return back()->with('status', 'Invitation sent to '.$invitation->email.'.');
    }
}
