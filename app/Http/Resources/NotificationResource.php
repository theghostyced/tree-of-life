<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data;

        return [
            'id' => $this->id,
            'category' => $data['category'] ?? 'general',
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
            'actions' => $data['actions'] ?? [],
            'illustration' => $data['illustration'] ?? null,
            'read' => $this->read_at !== null,
            'createdAt' => $this->created_at->getTimestampMs(),
        ];
    }
}
