<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateUserInvitation;
use App\Actions\SendInvitationMail;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkInvitationActionRequest;
use App\Http\Requests\Admin\StoreInvitationRequest;
use App\Models\InvitationImport;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
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
                'deliveryFailed' => $invitation->delivery_failed_at !== null,
                'deliveryError' => $invitation->delivery_error,
            ]);

        $latestImport = InvitationImport::query()->latest('id')->first();

        return Inertia::render('admin/invitations/Index', [
            'invitations' => $invitations,
            'activeImport' => $latestImport === null ? null : [
                'id' => $latestImport->id,
                'filename' => $latestImport->filename,
                'status' => $latestImport->status->value,
                'totalRows' => $latestImport->total_rows,
                'invitedCount' => $latestImport->invited_count,
                'skippedCount' => $latestImport->skipped_count,
                'invalidCount' => $latestImport->invalid_count,
                'rowErrors' => $latestImport->row_errors ?? [],
            ],
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

        $delivered = app(SendInvitationMail::class)->handle($invitation, $token);

        return redirect()
            ->route('admin.invitations.index')
            ->with('status', $delivered
                ? 'Invitation sent to '.$invitation->email.'.'
                : 'Invitation created, but the email to '.$invitation->email.' could not be delivered. Retry it from the list.');
    }

    public function resend(UserInvitation $invitation): RedirectResponse
    {
        Gate::authorize('resend', $invitation);

        // Re-issue a fresh single-use token and extend the expiry, then re-send.
        $token = Str::random(64);
        $invitation->update([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(config('invitations.expiry_days')),
        ]);

        $delivered = app(SendInvitationMail::class)->handle($invitation, $token);

        return back()->with('status', $delivered
            ? 'Invitation resent to '.$invitation->email.'.'
            : 'The email to '.$invitation->email.' still could not be delivered.');
    }

    public function revoke(UserInvitation $invitation): RedirectResponse
    {
        Gate::authorize('revoke', $invitation);

        $invitation->update(['revoked_at' => now()]);

        return back()->with('status', 'Invitation to '.$invitation->email.' revoked.');
    }

    /**
     * Resend or revoke many invitations at once. Each is checked against the
     * policy, so invitations that are no longer pending are silently skipped.
     */
    public function bulk(BulkInvitationActionRequest $request): RedirectResponse
    {
        $action = $request->validated('action');
        $actor = $request->user();
        $invitations = UserInvitation::query()
            ->whereIn('id', $request->validated('ids'))
            ->get();

        $affected = 0;
        foreach ($invitations as $invitation) {
            if (! $actor->can($action, $invitation)) {
                continue;
            }

            if ($action === 'resend') {
                $token = Str::random(64);
                $invitation->update([
                    'token_hash' => hash('sha256', $token),
                    'expires_at' => now()->addDays(config('invitations.expiry_days')),
                ]);
                app(SendInvitationMail::class)->handle($invitation, $token);
            } else {
                $invitation->update(['revoked_at' => now()]);
            }

            $affected++;
        }

        $verb = $action === 'resend' ? 'Resent' : 'Revoked';

        return back()->with(
            'status',
            "{$verb} {$affected} ".str('invitation')->plural($affected).'.',
        );
    }
}
