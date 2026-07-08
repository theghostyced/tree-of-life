<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MessagesController extends Controller
{
    public function index(Request $request, ?Conversation $conversation = null): Response
    {
        $user = $request->user();

        if ($conversation !== null) {
            Gate::authorize('view', $conversation);
        }

        return Inertia::render('messages/Index', [
            'currentUserId' => $user->id,
            'conversations' => $this->summaries($user),
            'selectedId' => $conversation?->id,
            'thread' => $conversation ? $this->thread($conversation, $user, $request) : null,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function summaries(User $user): array
    {
        $conversations = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->with(['pairing.entrepreneur', 'pairing.mentor', 'participants'])
            ->orderByRaw('last_message_at IS NULL, last_message_at DESC')
            ->get();

        return $conversations->map(function (Conversation $conversation) use ($user) {
            return [
                'id' => $conversation->id,
                'other' => $this->userSummary($this->otherUser($conversation, $user)),
                'last_message_preview' => $conversation->last_message_preview,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'unread_count' => $conversation->unreadCountFor($user),
                'is_active' => $conversation->isActive(),
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    private function thread(Conversation $conversation, User $user, Request $request): array
    {
        $messages = $conversation->messages()->orderByDesc('id')->limit(30)->get()->sortBy('id')->values();

        return [
            'conversation' => [
                'id' => $conversation->id,
                'other' => $this->userSummary($this->otherUser($conversation, $user)),
                'is_active' => $conversation->isActive(),
                'pairing_id' => $conversation->pairing_id,
                'other_last_read_message_id' => $conversation->otherParticipant($user)?->last_read_message_id,
            ],
            'messages' => MessageResource::collection($messages)->toArray($request),
        ];
    }

    private function otherUser(Conversation $conversation, User $user): User
    {
        $otherId = $conversation->otherParticipant($user)->user_id;

        return $conversation->pairing->entrepreneur->id === $otherId
            ? $conversation->pairing->entrepreneur
            : $conversation->pairing->mentor;
    }

    /** @return array<string, mixed> */
    private function userSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'initials' => Str::of($user->name)->explode(' ')->take(2)->map(fn ($p) => Str::substr($p, 0, 1))->implode(''),
            'role' => $user->role->value,
        ];
    }
}
