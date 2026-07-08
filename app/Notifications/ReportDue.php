<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Meeting;
use App\Support\Notifications\NotificationData;
use App\Support\Notifications\NotificationMail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The mentor, an hour after a call, asking for the report. In app plus email.
 */
class ReportDue extends Notification
{
    public function __construct(public Meeting $meeting) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    protected function data(object $notifiable): NotificationData
    {
        $entrepreneur = $this->meeting->pairing->entrepreneur->name;

        return new NotificationData(
            category: NotificationCategory::Report,
            title: 'Submit your session report',
            body: "Please share how your call with {$entrepreneur} went.",
            actions: [['label' => 'Submit report', 'url' => '/mentor/meetings']],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return NotificationMail::build(
            $this->data($notifiable),
            subject: 'Please submit your session report',
            greeting: "Hi {$notifiable->name},",
        );
    }
}
