<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class DocumentRequirement implements Arrayable
{
    public function __construct(
        public string $type,
        public string $label,
        public ?string $uploaded,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'label' => $this->label,
            'uploaded' => $this->uploaded,
        ];
    }
}
