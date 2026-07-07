<?php

// database/factories/ConversationFactory.php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Pairing;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Conversation> */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return ['pairing_id' => Pairing::factory()];
    }
}
