<?php

namespace App\Data;

use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class ProfileData implements Arrayable
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $avatarUrl,
    ) {}

    public static function forUser(User $user): self
    {
        return new self(
            name: $user->name,
            email: $user->email,
            avatarUrl: $user->avatarUrl(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'avatarUrl' => $this->avatarUrl,
        ];
    }
}
