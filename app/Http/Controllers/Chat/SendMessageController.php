<?php

namespace App\Http\Controllers\Chat;

use App\Actions\Chat\PostMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SendMessageController extends Controller
{
    public function store(StoreMessageRequest $request, Conversation $conversation, PostMessage $action): JsonResponse
    {
        Gate::authorize('sendMessage', $conversation);

        $message = $action->handle($conversation, $request->user(), $request->string('body')->trim()->toString());

        return MessageResource::make($message)->response()->setStatusCode(201);
    }
}
