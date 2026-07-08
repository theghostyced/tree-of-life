<?php

namespace App\Support\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Builds a MailMessage that renders the shared professional email template from
 * a notification's payload. Pass an absolute illustration URL (e.g.
 * asset('images/illustrations/foo.svg')) to show a hero image.
 */
class NotificationMail
{
    public static function build(
        NotificationData $data,
        string $subject,
        ?string $greeting = null,
        ?string $illustrationUrl = null,
    ): MailMessage {
        $action = $data->actions[0] ?? null;

        return (new MailMessage)
            ->subject($subject)
            ->view('mail.notification', [
                'appName' => config('app.name'),
                'greeting' => $greeting,
                'title' => $data->title,
                'body' => $data->body,
                'actionText' => $action['label'] ?? null,
                'actionUrl' => isset($action['url']) ? url($action['url']) : null,
                'illustrationUrl' => $illustrationUrl,
                'preheader' => $data->body,
            ]);
    }
}
