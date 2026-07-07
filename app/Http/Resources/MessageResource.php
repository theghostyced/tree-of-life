<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
{
    public static $wrap = 'message';

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_user_id,
            'type' => $this->type->value,
            'body' => $this->body,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
