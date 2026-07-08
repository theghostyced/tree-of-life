<?php

namespace App\Enums;

/**
 * Groups notifications for the bell (icon and section) and keeps the stored
 * `data.category` value type safe at the call site.
 */
enum NotificationCategory: string
{
    case Meeting = 'meeting';
    case Report = 'report';
    case Onboarding = 'onboarding';

    public function label(): string
    {
        return match ($this) {
            self::Meeting => 'Meetings',
            self::Report => 'Reports',
            self::Onboarding => 'Onboarding',
        };
    }
}
