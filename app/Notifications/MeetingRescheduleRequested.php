<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\MeetingReschedule;
use App\Support\Notifications\NotificationData;
use App\Support\Notifications\NotificationMail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The mentor, when an entrepreneur proposes a new time for a call. The meeting
 * keeps its original time until the mentor accepts.
 */
class MeetingRescheduleRequested extends Notification
{
    public function __construct(public MeetingReschedule $reschedule) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    protected function data(object $notifiable): NotificationData
    {
        $timezone = $this->reschedule->meeting->timezone;
        $from = $this->reschedule->previous_starts_at->timezone($timezone)->format('D j M, g:i A');
        $to = $this->reschedule->new_starts_at->timezone($timezone)->format('D j M, g:i A');
        $who = $this->reschedule->requestedBy->name;

        $body = "{$who} asked to move your call from {$from} to {$to}.";

        if ($this->reschedule->reason !== null && $this->reschedule->reason !== '') {
            $body .= " They said: \"{$this->reschedule->reason}\"";
        }

        return new NotificationData(
            category: NotificationCategory::Meeting,
            title: 'Reschedule requested',
            body: $body,
            actions: [['label' => 'Review request', 'url' => '/mentor/dashboard']],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return NotificationMail::build(
            $this->data($notifiable),
            subject: 'A mentee asked to move your call',
            greeting: "Hi {$notifiable->name},",
        );
    }
}
