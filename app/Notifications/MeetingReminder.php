<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Meeting;
use App\Support\Notifications\NotificationData;
use App\Support\Notifications\NotificationMail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent to both parties before a meeting. A day before it is email only; an hour
 * before it is email plus in app.
 */
class MeetingReminder extends Notification
{
    /**
     * @param  string  $lead  'day' or 'hour'
     */
    public function __construct(public Meeting $meeting, public string $lead) {}

    public function via(object $notifiable): array
    {
        return $this->lead === 'day'
            ? ['mail']
            : ['database', 'broadcast', 'mail'];
    }

    protected function data(object $notifiable): NotificationData
    {
        $isMentor = $notifiable->id === $this->meeting->pairing->mentor_user_id;
        $other = $isMentor
            ? $this->meeting->pairing->entrepreneur->name
            : $this->meeting->pairing->mentor->name;
        $starts = $this->meeting->starts_at->timezone($this->meeting->timezone);
        $url = $isMentor ? '/mentor/meetings' : '/entrepreneur/meetings';

        return new NotificationData(
            category: NotificationCategory::Meeting,
            title: $this->lead === 'day' ? 'Your call is tomorrow' : 'Your call is in an hour',
            body: $this->lead === 'day'
                ? "Your call with {$other} is set for {$starts->format('D j M, g:i A')}."
                : "Your call with {$other} starts at {$starts->format('g:i A')}.",
            actions: [['label' => 'View meeting', 'url' => $url]],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return NotificationMail::build(
            $this->data($notifiable),
            subject: $this->lead === 'day' ? 'Your call is tomorrow' : 'Your call is in an hour',
            greeting: "Hi {$notifiable->name},",
        );
    }
}
