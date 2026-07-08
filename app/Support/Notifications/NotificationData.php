<?php

namespace App\Support\Notifications;

use App\Enums\NotificationCategory;

/**
 * The shared payload every notification stores (database) and broadcasts. Kept
 * small and presentation ready so the bell and any email header can read it.
 */
class NotificationData
{
    /**
     * @param  array<int, array{label: string, url: string}>  $actions
     */
    public function __construct(
        public NotificationCategory $category,
        public string $title,
        public string $body,
        public array $actions = [],
        public ?string $illustration = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'category' => $this->category->value,
            'title' => $this->title,
            'body' => $this->body,
            'actions' => $this->actions,
            'illustration' => $this->illustration,
        ];
    }
}
