<?php

namespace App\Data;

use App\Models\User;
use App\Support\ProfileCompleteness;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class OnboardingProgress implements Arrayable
{
    /**
     * @param  array<int, string>  $missing
     */
    public function __construct(
        public string $status,
        public int $total,
        public int $completed,
        public int $remaining,
        public bool $isComplete,
        public array $missing,
    ) {}

    public static function forUser(User $user): self
    {
        $completeness = app(ProfileCompleteness::class);
        $missing = array_values($completeness->missingItems($user));
        $total = $completeness->requiredCount($user);
        $remaining = count($missing);

        return new self(
            status: $user->account_status->value,
            total: $total,
            completed: max(0, $total - $remaining),
            remaining: $remaining,
            isComplete: $remaining === 0,
            missing: $missing,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'total' => $this->total,
            'completed' => $this->completed,
            'remaining' => $this->remaining,
            'isComplete' => $this->isComplete,
            'missing' => $this->missing,
        ];
    }
}
