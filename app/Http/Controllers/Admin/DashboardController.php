<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvitation;
use App\Support\ProfileCompleteness;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, ProfileCompleteness $completeness): Response
    {
        $users = User::query()->get();

        $mentors = $users->where('role', UserRole::Mentor);
        $entrepreneurs = $users->where('role', UserRole::Entrepreneur);

        // "Ready to pair" = an active account with a fully completed profile.
        $ready = fn (User $user): bool => ! $user->isDeactivated()
            && $completeness->missingItems($user) === [];

        $pendingInvites = UserInvitation::query()
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->count();

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'people' => $users->count(),
                'deactivated' => $users->filter->isDeactivated()->count(),
                'mentors' => $mentors->count(),
                'entrepreneurs' => $entrepreneurs->count(),
                'pendingInvites' => $pendingInvites,
            ],
            'readiness' => [
                'mentorsReady' => $mentors->filter($ready)->count(),
                'mentorsTotal' => $mentors->count(),
                'entrepreneursReady' => $entrepreneurs->filter($ready)->count(),
                'entrepreneursTotal' => $entrepreneurs->count(),
            ],
            'recent' => $users->sortByDesc('id')->take(6)->values()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role->value,
                    'status' => $user->account_status->value,
                    'joinedAt' => $user->created_at->getTimestampMs(),
                ]),
            'pendingInvitations' => UserInvitation::query()
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (UserInvitation $invitation): array => [
                    'id' => $invitation->id,
                    'name' => $invitation->name,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'sentAt' => $invitation->created_at->getTimestampMs(),
                ]),
        ]);
    }
}
