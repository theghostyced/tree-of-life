<?php

namespace App\Http\Controllers\Auth;

use App\Actions\AcceptUserInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

        // New entrepreneurs and mentors start in onboarding; others go to their dashboard.
        $destination = $user->role->hasProfile()
            ? $user->role->onboardingPath()
            : $user->role->homePath();

        return redirect($destination);
    }

    /**
     * Resolve a still-pending invitation by its raw token, or abort:
     * 404 for an unknown token, 410 Gone for an expired/revoked/used one.
     *
     * Both refusals are logged. The page shown to the visitor is deliberately
     * vague — they are unauthenticated and hold only a token — so these lines
     * are the only record of why a link was turned away. Neither the token,
     * its hash, nor the invited email is written out: the invitation id is
     * enough to look the rest up, and keeps credentials and PII out of logs.
     */
    private function pendingInvitationOrAbort(string $token): UserInvitation
    {
        $invitation = UserInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        $method = request()->method();

        if ($invitation === null) {
            Log::warning('Invitation token not recognised', ['method' => $method]);

            abort(404);
        }

        if (! $invitation->isPending()) {
            Log::warning('Invitation link refused', [
                'invitation_id' => $invitation->id,
                'status' => $invitation->status()->value,
                'method' => $method,
            ]);

            abort(410);
        }

        return $invitation;
    }
}
