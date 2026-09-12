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
     * @param  array<int, string>  $expertise_areas
     * @param  array<int, string>  $industry_focus
     */
    public function __construct(
        public array $expertise_areas,
        public array $industry_focus,
        public ?int $years_experience,
        public ?string $afcfta_knowledge,
        public ?string $availability,
    ) {}

    public static function fromProfile(?MentorProfile $profile): self
    {
        return new self(
            expertise_areas: $profile->expertise_areas ?? [],
            industry_focus: $profile->industry_focus ?? [],
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
            'expertise_areas' => $this->expertise_areas,
            'industry_focus' => $this->industry_focus,
            'years_experience' => $this->years_experience,
            'afcfta_knowledge' => $this->afcfta_knowledge,
            'availability' => $this->availability,
        ];
    }
}
