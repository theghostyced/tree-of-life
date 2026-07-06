<?php

namespace App\Http\Controllers\Admin;

use App\Data\EntrepreneurProfileFields;
use App\Data\MentorProfileFields;
use App\Enums\AccountStatus;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
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
}
