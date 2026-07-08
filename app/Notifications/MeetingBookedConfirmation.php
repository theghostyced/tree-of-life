<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Meeting;
use App\Support\Notifications\NotificationData;
use App\Support\Notifications\NotificationMail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The entrepreneur who booked the call, as a confirmation.
 */
class MeetingBookedConfirmation extends Notification
{
    public function __construct(public Meeting $meeting) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    protected function data(object $notifiable): NotificationData
    {
        $when = $this->meeting->starts_at->timezone($this->meeting->timezone)->format('D j M, g:i A');
        $mentor = $this->meeting->pairing->mentor->name;

        return new NotificationData(
            category: NotificationCategory::Meeting,
            title: 'Your call is booked',
            body: "Your call with {$mentor} is set for {$when}.",
            actions: [['label' => 'View meeting', 'url' => '/entrepreneur/meetings']],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return NotificationMail::build(
            $this->data($notifiable),
            subject: 'Your call is booked',
            greeting: "Hi {$notifiable->name},",
        );
    }
}
