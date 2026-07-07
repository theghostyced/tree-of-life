<?php

namespace App\Observers;

use App\Actions\Chat\ProvisionConversation;
use App\Models\Pairing;

class PairingObserver
{
    public function __construct(private ProvisionConversation $provision) {}

    public function created(Pairing $pairing): void
    {
        $this->provision->handle($pairing);
    }
}
