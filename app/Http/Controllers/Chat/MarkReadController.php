<?php

namespace App\Http\Controllers\Chat;

use App\Events\MessageRead;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MarkReadController extends Controller
{
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();
        $latestId = $conversation->messages()->max('id');

        $conversation->participants()->where('user_id', $user->id)->update([
            'last_read_at' => now(),
            'last_read_message_id' => $latestId,
        ]);

        MessageRead::dispatch($conversation->id, $user->id, $latestId);

        return response()->json(['ok' => true]);
    }
}
