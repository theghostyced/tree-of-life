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
    private const PER_PAGE = 12;

    /**
     * The mentors hub — the entrepreneur's chosen mentors plus a directory to
     * find and add more.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! OnboardingProgress::forUser($user)->isComplete) {
            return redirect()->route('entrepreneur.dashboard');
        }

        $chosenIds = $user->mentors()->pluck('users.id');

        $search = trim((string) $request->query('search', ''));
        $focus = trim((string) $request->query('focus', ''));

        $mentors = User::query()
            ->availableMentor()
            ->whereNotIn('id', $chosenIds)
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
            // Lazy: these only run on a full page load, not on the partial
            // reloads that search / filter / paginate the directory.
            'yourMentors' => fn () => $user->mentors()
                ->with('mentorProfile')
                ->orderBy('name')
                ->get()
                ->map(fn (User $mentor) => MentorCard::forUser($mentor)->toArray())
                ->values(),
            'focusAreas' => fn () => $this->focusAreas(),
        ]);
    }

    /**
     * One of the entrepreneur's chosen mentors — a dedicated detail page.
     */
    public function show(Request $request, User $mentor): Response|RedirectResponse
    {
        $chosen = $request->user()
            ->mentors()
            ->with('mentorProfile')
            ->whereKey($mentor->id)
            ->first();

        // Not one of their mentors — send them back to the hub.
        if ($chosen === null) {
            return redirect()->route('entrepreneur.mentors.index');
        }

        return Inertia::render('entrepreneur/Mentor', [
            'mentor' => MentorCard::forUser($chosen)->toArray(),
            'pairedAt' => $chosen->pivot->created_at?->toIso8601String(),
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
