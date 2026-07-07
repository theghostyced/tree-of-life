<?php

// database/factories/MessageFactory.php

namespace Database\Factories;

use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Message> */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_user_id' => null,
            'type' => MessageType::Text,
            'body' => fake()->sentence(),
        ];
    }
}
