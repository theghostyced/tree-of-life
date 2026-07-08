<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $pendingInvites = fn (): Builder => UserInvitation::query()
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'people' => User::query()->count(),
                'deactivated' => User::query()->where('account_status', AccountStatus::Deactivated)->count(),
                'mentors' => User::query()->where('role', UserRole::Mentor)->count(),
                'entrepreneurs' => User::query()->where('role', UserRole::Entrepreneur)->count(),
                'pendingInvites' => $pendingInvites()->count(),
            ],
            'readiness' => [
                'mentorsReady' => $this->readyCount(UserRole::Mentor),
                'mentorsTotal' => User::query()->where('role', UserRole::Mentor)->count(),
                'entrepreneursReady' => $this->readyCount(UserRole::Entrepreneur),
                'entrepreneursTotal' => User::query()->where('role', UserRole::Entrepreneur)->count(),
            ],
            'growth' => $this->growth(),
            'recent' => User::query()->latest('id')->limit(6)->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role->value,
                    'status' => $user->account_status->value,
                    'joinedAt' => $user->created_at->getTimestampMs(),
                ]),
            'pendingInvitations' => $pendingInvites()->latest('id')->limit(5)->get()
                ->map(fn (UserInvitation $invitation): array => [
                    'id' => $invitation->id,
                    'name' => $invitation->name,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'sentAt' => $invitation->created_at->getTimestampMs(),
                ]),
        ]);
    }

    /**
     * New members per month over the last 6 months, split by role — one
     * aggregate query per month, so no user rows are pulled into PHP.
     *
     * @return array<int, array<string, mixed>>
     */
    private function growth(): array
    {
        return collect(range(5, 0))->map(function (int $back): array {
            $month = now()->subMonths($back);

            $row = User::query()
                ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->selectRaw('sum(case when role = ? then 1 else 0 end) as mentors', [UserRole::Mentor->value])
                ->selectRaw('sum(case when role = ? then 1 else 0 end) as entrepreneurs', [UserRole::Entrepreneur->value])
                ->first();

            return [
                'month' => $month->format('M'),
                'mentors' => (int) ($row->mentors ?? 0),
                'entrepreneurs' => (int) ($row->entrepreneurs ?? 0),
            ];
        })->values()->all();
    }

    /**
     * Count of "ready to pair" users for a role — approved, every profile field
     * filled, and every required document present — computed entirely in SQL.
     */
    private function readyCount(UserRole $role): int
    {
        $query = User::query()
            ->where('role', $role)
            ->where('account_status', AccountStatus::Approved);

        if ($role === UserRole::Mentor) {
            $query->whereHas('mentorProfile', fn (Builder $p) => $p
                ->whereNotNull('primary_expertise')
                ->whereNotNull('years_experience')
                ->whereNotNull('afcfta_knowledge')
                ->whereNotNull('availability')
                ->whereNotNull('industry_focus'));
        } else {
            $query->whereHas('entrepreneurProfile', fn (Builder $p) => $p
                ->whereNotNull('business_name')
                ->whereNotNull('business_description')
                ->whereNotNull('business_email')
                ->whereNotNull('business_phone_number')
                ->whereNotNull('years_in_operation')
                ->whereNotNull('employee_count')
                ->whereNotNull('sector'));
        }

        // Every required document type present (distinct-type count matches).
        $types = array_map(fn (DocumentType $t) => $t->value, DocumentType::requiredFor($role));
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $query->whereRaw(
            "(select count(distinct document_type) from user_documents
              where user_documents.user_id = users.id
              and document_type in ($placeholders)) = ?",
            [...$types, count($types)],
        );

        return $query->count();
    }
}
