<?php

namespace App\Data;

use App\Models\MentorProfile;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class MentorProfileFields implements Arrayable
{
    /**
     * @param  array<int, string>  $industry_focus
     */
    public function __construct(
        public ?string $primary_expertise,
        public array $industry_focus,
        public ?int $years_experience,
        public ?string $afcfta_knowledge,
        public ?string $availability,
    ) {}

    public static function fromProfile(?MentorProfile $profile): self
    {
        return new self(
            primary_expertise: $profile?->primary_expertise,
            industry_focus: $profile?->industry_focus ?? [],
            years_experience: $profile?->years_experience,
            afcfta_knowledge: $profile?->afcfta_knowledge,
            availability: $profile?->availability,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'primary_expertise' => $this->primary_expertise,
            'industry_focus' => $this->industry_focus,
            'years_experience' => $this->years_experience,
            'afcfta_knowledge' => $this->afcfta_knowledge,
            'availability' => $this->availability,
        ];
    }
}
