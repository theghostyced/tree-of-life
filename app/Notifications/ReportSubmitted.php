<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Meeting;
use App\Support\Notifications\NotificationData;

/**
 * Admins, when a mentor submits a meeting report. In app only.
 */
class ReportSubmitted extends Notification
{
    public function __construct(public Meeting $meeting) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    protected function data(object $notifiable): NotificationData
    {
        $mentor = $this->meeting->pairing->mentor->name;
        $entrepreneur = $this->meeting->pairing->entrepreneur->name;

        return new NotificationData(
            category: NotificationCategory::Report,
            title: 'New meeting report',
            body: "{$mentor} submitted a report for the call with {$entrepreneur}.",
            actions: [['label' => 'View entrepreneur', 'url' => "/admin/users/{$this->meeting->pairing->entrepreneur_user_id}"]],
        );
    }
}
