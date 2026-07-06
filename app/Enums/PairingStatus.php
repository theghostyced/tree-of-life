<?php

namespace App\Enums;

/**
 * Lifecycle of a mentor pairing. Created when an entrepreneur selects a
 * mentor; ended when the relationship stops.
 */
enum PairingStatus: string
{
    case Active = 'active';
    case Ended = 'ended';
}
