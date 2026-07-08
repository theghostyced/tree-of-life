<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Meeting;
use App\Support\Notifications\NotificationData;
use App\Support\Notifications\NotificationMail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The mentor whose time was booked.
 */
class MeetingBooked extends Notification
{
    public function __construct(public Meeting $meeting) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    protected function data(object $notifiable): NotificationData
    {
        $when = $this->meeting->starts_at->timezone($this->meeting->timezone)->format('D j M, g:i A');
        $entrepreneur = $this->meeting->pairing->entrepreneur->name;

        return new NotificationData(
            category: NotificationCategory::Meeting,
            title: 'New call booked',
            body: "{$entrepreneur} booked a call with you for {$when}.",
            actions: [['label' => 'View meeting', 'url' => '/mentor/meetings']],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return NotificationMail::build(
            $this->data($notifiable),
            subject: 'A new call has been booked',
            greeting: "Hi {$notifiable->name},",
        );
    }
}
