<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Support\Notifications\NotificationData;

/**
 * Admins, when a user finishes onboarding and is ready. In app only.
 */
class UserCompletedOnboarding extends Notification
{
    public function __construct(public User $user) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    protected function data(object $notifiable): NotificationData
    {
        $role = $this->user->role->label();

        return new NotificationData(
            category: NotificationCategory::Onboarding,
            title: 'Onboarding complete',
            body: "{$this->user->name} ({$role}) finished onboarding and is ready.",
            actions: [['label' => 'View user', 'url' => "/admin/users/{$this->user->id}"]],
        );
    }
}
