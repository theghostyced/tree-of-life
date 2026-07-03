<?php

namespace App\Http\Controllers\Auth;

use App\Actions\AcceptUserInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InvitationAcceptanceController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = $this->pendingInvitationOrAbort($token);

        return Inertia::render('auth/AcceptInvitation', [
            'invitation' => [
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'name' => $invitation->name,
            ],
            'token' => $token,
        ]);
    }

    public function store(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = $this->pendingInvitationOrAbort($token);

        $user = app(AcceptUserInvitation::class)->handle(
            $invitation,
            $request->validated('name'),
            $request->validated('password'),
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect($user->role->homePath($user->isApproved()));
    }

    /**
     * Resolve a still-pending invitation by its raw token, or abort:
     * 404 for an unknown token, 410 Gone for an expired/revoked/used one.
     */
    private function pendingInvitationOrAbort(string $token): UserInvitation
    {
        $invitation = UserInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        abort_if($invitation === null, 404);
        abort_unless($invitation->isPending(), 410);

        return $invitation;
    }
}
