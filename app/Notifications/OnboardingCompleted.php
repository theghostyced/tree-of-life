<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Support\Notifications\NotificationData;
use App\Support\Notifications\NotificationMail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The user themselves, the moment their profile first becomes complete.
 */
class OnboardingCompleted extends Notification
{
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    protected function data(object $notifiable): NotificationData
    {
        $isMentor = $notifiable->role === UserRole::Mentor;

        return new NotificationData(
            category: NotificationCategory::Onboarding,
            title: 'You are all set',
            body: $isMentor
                ? 'Your profile is complete. You can now set your availability and take bookings.'
                : 'Your profile is complete. You can now browse and add mentors.',
            actions: [
                $isMentor
                    ? ['label' => 'Set availability', 'url' => '/mentor/availability']
                    : ['label' => 'Browse mentors', 'url' => '/entrepreneur/mentors'],
            ],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return NotificationMail::build(
            $this->data($notifiable),
            subject: 'Your profile is complete',
            greeting: "Hi {$notifiable->name},",
            illustrationUrl: asset('images/illustrations/achievement-confirm.svg'),
        );
    }
}
