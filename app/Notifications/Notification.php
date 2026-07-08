<?php

namespace App\Notifications;

use App\Support\Notifications\NotificationData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification as BaseNotification;

/**
 * Base for every Tolfund notification. Concrete notifications declare their
 * channels via `via()` and build one `data()` payload reused by the database,
 * broadcast, and (where present) mail representations. Always queued.
 */
abstract class Notification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    abstract public function via(object $notifiable): array;

    /**
     * The in app / broadcast payload for this recipient.
     */
    abstract protected function data(object $notifiable): NotificationData;

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->data($notifiable)->toArray();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->data($notifiable)->toArray());
    }
}
