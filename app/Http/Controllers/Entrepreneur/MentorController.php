<?php

namespace App\Http\Controllers\Entrepreneur;

use App\Data\MentorCard;
use App\Data\OnboardingProgress;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MentorController extends Controller
{
    private const PER_PAGE = 9;

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // The directory is only meaningful once onboarding is done and the
        // entrepreneur has not already chosen a mentor.
        if (! OnboardingProgress::forUser($user)->isComplete || $user->mentorPairing()->exists()) {
            return redirect()->route('entrepreneur.dashboard');
        }

        $search = trim((string) $request->query('search', ''));
        $focus = trim((string) $request->query('focus', ''));

        $mentors = User::query()
            ->availableMentor()
            ->when($search !== '', fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhereHas('mentorProfile', fn (Builder $p) => $p
                        ->where('primary_expertise', 'like', "%{$search}%")
                        ->orWhere('afcfta_knowledge', 'like', "%{$search}%"))
            ))
            ->when($focus !== '', fn (Builder $q) => $q->whereHas(
                'mentorProfile',
                fn (Builder $p) => $p->whereJsonContains('industry_focus', $focus),
            ))
            ->with('mentorProfile')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $mentor) => MentorCard::forUser($mentor)->toArray());

        return Inertia::render('entrepreneur/Mentors', [
            'mentors' => $mentors,
            'filters' => ['search' => $search, 'focus' => $focus],
            // Lazy: the facet scan only runs on a full page load, not on the
            // partial reloads that search / filter / paginate trigger.
            'focusAreas' => fn () => $this->focusAreas(),
        ]);
    }

    /**
     * De-duplicated, sorted focus areas across every available mentor — the
     * filter options.
     *
     * @return array<int, string>
     */
    private function focusAreas(): array
    {
        return User::query()
            ->availableMentor()
            ->with('mentorProfile:id,user_id,industry_focus')
            ->get()
            ->flatMap(fn (User $mentor) => $mentor->mentorProfile?->industry_focus ?? [])
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
