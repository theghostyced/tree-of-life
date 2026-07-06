<?php

namespace App\Http\Controllers\Admin;

use App\Data\EntrepreneurProfileFields;
use App\Data\MentorProfileFields;
use App\Enums\AccountStatus;
use App\Enums\DocumentType;
use App\Enums\MeetingStatus;
use App\Enums\PairingStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkUserActionRequest;
use App\Models\Pairing;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->latest('id')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'status' => $user->account_status->value,
                'verified' => $user->email_verified_at !== null,
                'joinedAt' => $user->created_at->getTimestampMs(),
            ]);

        return Inertia::render('admin/users/Index', ['users' => $users]);
    }

    public function show(User $user): Response
    {
        Gate::authorize('view', $user);

        $user->loadMissing(['entrepreneurProfile', 'mentorProfile', 'company', 'documents']);

        return Inertia::render('admin/users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'status' => $user->account_status->value,
                'phone' => $user->phone_number,
                'verified' => $user->email_verified_at !== null,
                'joinedAt' => $user->created_at->getTimestampMs(),
                'statusChangedAt' => $user->account_status_changed_at?->getTimestampMs(),
                'company' => $user->company?->name,
                'profile' => $this->profileFor($user),
                'documents' => $this->documentsFor($user),
            ],
            'pairings' => $this->pairingsFor($user),
        ]);
    }

    public function deactivate(User $user): RedirectResponse
    {
        Gate::authorize('deactivate', $user);

        $user->update([
            'account_status' => AccountStatus::Deactivated,
            'account_status_changed_at' => now(),
        ]);

        return back()->with('status', "{$user->name}'s access has been revoked.");
    }

    public function reactivate(User $user): RedirectResponse
    {
        Gate::authorize('reactivate', $user);

        $user->update([
            'account_status' => AccountStatus::Approved,
            'account_status_changed_at' => now(),
        ]);

        return back()->with('status', "{$user->name}'s access has been restored.");
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', "{$user->name} has been removed.");
    }

    /**
     * Apply one lifecycle action to many users at once. Each target is checked
     * against the policy, so accounts the admin may not touch (their own) are
     * silently skipped rather than failing the whole batch.
     */
    public function bulk(BulkUserActionRequest $request): RedirectResponse
    {
        $action = $request->validated('action');
        $actor = $request->user();
        $users = User::query()->whereIn('id', $request->validated('ids'))->get();

        $affected = 0;
        foreach ($users as $user) {
            if (! $actor->can($action, $user)) {
                continue;
            }

            match ($action) {
                'deactivate' => $user->update([
                    'account_status' => AccountStatus::Deactivated,
                    'account_status_changed_at' => now(),
                ]),
                'reactivate' => $user->update([
                    'account_status' => AccountStatus::Approved,
                    'account_status_changed_at' => now(),
                ]),
                'delete' => $user->delete(),
            };

            $affected++;
        }

        $verb = match ($action) {
            'deactivate' => 'Revoked access for',
            'reactivate' => 'Restored access for',
            'delete' => 'Removed',
        };

        return back()->with(
            'status',
            "{$verb} {$affected} ".str('account')->plural($affected).'.',
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function profileFor(User $user): ?array
    {
        return match ($user->role) {
            UserRole::Entrepreneur => EntrepreneurProfileFields::fromProfile($user->entrepreneurProfile)->toArray(),
            UserRole::Mentor => MentorProfileFields::fromProfile($user->mentorProfile)->toArray(),
            default => null,
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documentsFor(User $user): array
    {
        $documents = $user->documents->keyBy(fn (UserDocument $doc) => $doc->document_type->value);

        return collect(DocumentType::requiredFor($user->role))
            ->map(fn (DocumentType $type): array => [
                'id' => $documents->get($type->value)?->id,
                'type' => $type->value,
                'label' => $type->label(),
                'uploaded' => $documents->get($type->value)?->original_name,
            ])->all();
    }

    /**
     * Pairings shaped relative to the viewed user: a mentor's page lists
     * their entrepreneurs, an entrepreneur's page lists their mentors.
     *
     * @return array{active: array<int, array<string, mixed>>, ended: array<int, array<string, mixed>>}
     */
    private function pairingsFor(User $user): array
    {
        $pairings = Pairing::query()
            ->where(fn ($q) => $q
                ->where('mentor_user_id', $user->id)
                ->orWhere('entrepreneur_user_id', $user->id))
            ->with([
                'mentor:id,name',
                'entrepreneur:id,name,company_id',
                'entrepreneur.company:id,name',
            ])
            ->latest()
            ->get();

        $shape = function (Pairing $pairing) use ($user): array {
            $counterpart = $pairing->mentor_user_id === $user->id
                ? $pairing->entrepreneur
                : $pairing->mentor;

            $lastMeeting = $pairing->meetings()
                ->where('status', MeetingStatus::Completed)
                ->latest('ends_at')
                ->first();

            return [
                'id' => $pairing->id,
                'userId' => $counterpart->id,
                'name' => $counterpart->name,
                // Company describes the counterpart, so it only applies when
                // the counterpart is the entrepreneur (mentors have none).
                'company' => $counterpart->is($pairing->entrepreneur)
                    ? $pairing->entrepreneur->company?->name
                    : null,
                'since' => $pairing->created_at->getTimestampMs(),
                'lastMeetingAt' => $lastMeeting?->ends_at->getTimestampMs(),
                'endedAt' => $pairing->ended_at?->getTimestampMs(),
            ];
        };

        return [
            'active' => $pairings->where('status', PairingStatus::Active)->map($shape)->values()->all(),
            'ended' => $pairings->where('status', PairingStatus::Ended)->map($shape)->values()->all(),
        ];
    }
}
