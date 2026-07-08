<?php

namespace App\Http\Middleware;

use App\Models\Message;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
                'role' => $request->user()?->role?->value,
                'unreadMessages' => $request->user()
                    ? Message::query()
                        ->whereHas('conversation.participants', fn ($q) => $q->where('user_id', $request->user()->id))
                        ->where('sender_user_id', '!=', $request->user()->id)
                        ->whereNotExists(function ($q) use ($request) {
                            $q->selectRaw('1')->from('conversation_participants as cp')
                                ->whereColumn('cp.conversation_id', 'messages.conversation_id')
                                ->where('cp.user_id', $request->user()->id)
                                ->whereColumn('cp.last_read_message_id', '>=', 'messages.id');
                        })->count()
                    : 0,
            ],
        ];
    }
}
