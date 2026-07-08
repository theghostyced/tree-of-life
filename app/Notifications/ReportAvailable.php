<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Meeting;
use App\Support\Notifications\NotificationData;

/**
 * The entrepreneur, when their mentor files a report for their call. In app only.
 */
class ReportAvailable extends Notification
{
    public function __construct(public Meeting $meeting) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    protected function data(object $notifiable): NotificationData
    {
        $mentor = $this->meeting->pairing->mentor->name;

        return new NotificationData(
            category: NotificationCategory::Report,
            title: 'Session report ready',
            body: "{$mentor} filed the report for your recent call.",
            actions: [['label' => 'View meeting', 'url' => '/entrepreneur/meetings']],
        );
    }
}
