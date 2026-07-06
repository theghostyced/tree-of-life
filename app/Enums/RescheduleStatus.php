<?php

namespace App\Enums;

/**
 * A reschedule request is reviewed by the counterparty of whoever asked.
 */
enum RescheduleStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
