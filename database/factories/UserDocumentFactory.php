<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserDocument>
 */
class UserDocumentFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(DocumentType::cases());

        return [
            'user_id' => User::factory()->entrepreneur(),
            'document_type' => $type,
            'disk' => 'local',
            'path' => 'documents/'.fake()->uuid().'/'.$type->value.'.pdf',
            'original_name' => $type->value.'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 500_000),
            'uploaded_at' => now(),
        ];
    }
}
