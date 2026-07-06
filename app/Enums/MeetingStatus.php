<?php

namespace App\Enums;

/**
 * Meeting state machine: confirmed -> completed (only after starts_at),
 * confirmed -> cancelled.
 */
enum MeetingStatus: string
{
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
