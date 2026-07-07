<?php

namespace App\Console\Commands;

use App\Actions\Chat\ProvisionConversation;
use App\Models\Pairing;
use Illuminate\Console\Command;

class BackfillConversations extends Command
{
    protected $signature = 'chat:backfill-conversations';

    protected $description = 'Provision a conversation for every pairing that lacks one';

    public function handle(ProvisionConversation $provision): int
    {
        Pairing::query()->whereDoesntHave('conversation')->chunkById(200, function ($pairings) use ($provision) {
            foreach ($pairings as $pairing) {
                $provision->handle($pairing);
                $this->line("Provisioned conversation for pairing #{$pairing->id}");
            }
        });

        return self::SUCCESS;
    }
}
