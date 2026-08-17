<?php

namespace App\Http\Controllers\Entrepreneur;

use App\Actions\CreateUserInvitation;
use App\Actions\SendInvitationMail;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Entrepreneur\StoreEmployeeInvitationRequest;
use Illuminate\Http\RedirectResponse;

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

        $delivered = app(SendInvitationMail::class)->handle($invitation, $token);

        return back()->with('status', $delivered
            ? 'Invitation sent to '.$invitation->email.'.'
            : 'Invitation created, but the email to '.$invitation->email.' could not be delivered. Ask an admin to retry it.');
    }
}
