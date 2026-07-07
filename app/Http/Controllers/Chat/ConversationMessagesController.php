<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConversationMessagesController extends Controller
{
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $before = $request->integer('before');

        $messages = $conversation->messages()
            ->when($before, fn ($q) => $q->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->sortBy('id')
            ->values();

        return response()->json(['messages' => MessageResource::collection($messages)]);
    }
}
