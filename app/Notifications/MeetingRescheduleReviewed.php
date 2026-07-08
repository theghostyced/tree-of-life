<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Models\MeetingReschedule;
use App\Support\Notifications\NotificationData;
use App\Support\Notifications\NotificationMail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The person who requested a reschedule, once the other party accepts or
 * declines it.
 */
class MeetingRescheduleReviewed extends Notification
{
    public function __construct(public MeetingReschedule $reschedule, public bool $accepted) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    protected function data(object $notifiable): NotificationData
    {
        $url = $notifiable->role === UserRole::Mentor ? '/mentor/meetings' : '/entrepreneur/meetings';

        if ($this->accepted) {
            $when = $this->reschedule->new_starts_at
                ->timezone($this->reschedule->meeting->timezone)
                ->format('D j M, g:i A');

            return new NotificationData(
                category: NotificationCategory::Meeting,
                title: 'Reschedule accepted',
                body: "Your reschedule was accepted. The call is now set for {$when}.",
                actions: [['label' => 'View meeting', 'url' => $url]],
            );
        }

        return new NotificationData(
            category: NotificationCategory::Meeting,
            title: 'Reschedule declined',
            body: 'Your reschedule request was declined. The call keeps its original time.',
            actions: [['label' => 'View meeting', 'url' => $url]],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return NotificationMail::build(
            $this->data($notifiable),
            subject: $this->accepted ? 'Your reschedule was accepted' : 'Your reschedule was declined',
            greeting: "Hi {$notifiable->name},",
        );
    }
}
