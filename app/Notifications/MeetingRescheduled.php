<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\MeetingReschedule;
use App\Support\Notifications\NotificationData;
use App\Support\Notifications\NotificationMail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The entrepreneur, when their mentor moves a call directly. The mentor owns
 * the availability, so this is an announcement rather than a request.
 */
class MeetingRescheduled extends Notification
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

        $body = "{$who} moved your call from {$from} to {$to}.";

        if ($this->reschedule->reason !== null && $this->reschedule->reason !== '') {
            $body .= " They said: \"{$this->reschedule->reason}\"";
        }

        return new NotificationData(
            category: NotificationCategory::Meeting,
            title: 'Call moved',
            body: $body,
            actions: [['label' => 'View meeting', 'url' => '/entrepreneur/meetings']],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return NotificationMail::build(
            $this->data($notifiable),
            subject: 'Your mentor moved your call',
            greeting: "Hi {$notifiable->name},",
        );
    }
}
