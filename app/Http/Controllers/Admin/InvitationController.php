<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateUserInvitation;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvitationRequest;
use App\Mail\UserInvitationMail;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function index(): Response
    {
        $invitations = UserInvitation::query()
            ->with('inviter:id,name')
            ->latest()
            ->get()
            ->map(fn (UserInvitation $invitation): array => [
                'id' => $invitation->id,
                'name' => $invitation->name,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'status' => $invitation->status()->value,
                'invitedBy' => $invitation->inviter?->name ?? 'Unknown',
                'sentAt' => $invitation->created_at->getTimestampMs(),
                'expiresAt' => $invitation->expires_at->getTimestampMs(),
            ]);

        return Inertia::render('admin/invitations/Index', [
            'invitations' => $invitations,
        ]);
    }

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

    public function resend(UserInvitation $invitation): RedirectResponse
    {
        Gate::authorize('resend', $invitation);

        // Re-issue a fresh single-use token and extend the expiry, then re-send.
        $token = Str::random(64);
        $invitation->update([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($invitation->email)->queue(new UserInvitationMail($invitation, $token));

        return back()->with('status', 'Invitation resent to '.$invitation->email.'.');
    }

    public function revoke(UserInvitation $invitation): RedirectResponse
    {
        Gate::authorize('revoke', $invitation);

        $invitation->update(['revoked_at' => now()]);

        return back()->with('status', 'Invitation to '.$invitation->email.' revoked.');
    }
}
