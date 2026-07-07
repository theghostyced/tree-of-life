<?php

namespace App\Actions\Chat;

use App\Models\Conversation;
use App\Models\Pairing;
use Illuminate\Support\Facades\DB;

class ProvisionConversation
{
    public function handle(Pairing $pairing): Conversation
    {
        return DB::transaction(function () use ($pairing) {
            $conversation = Conversation::firstOrCreate(['pairing_id' => $pairing->id]);

            foreach ([$pairing->entrepreneur_user_id, $pairing->mentor_user_id] as $userId) {
                $conversation->participants()->firstOrCreate(['user_id' => $userId]);
            }

            return $conversation;
        });
    }
}
