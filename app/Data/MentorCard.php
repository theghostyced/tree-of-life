<?php

namespace App\Data;

use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;

/**
 * The public-facing shape of a mentor, shown in the directory and on the
 * entrepreneur's dashboard once paired.
 *
 * @implements Arrayable<string, mixed>
 */
class MentorCard implements Arrayable
{
    /**
     * @param  array<int, string>  $industries
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $expertise,
        public array $industries,
        public ?int $yearsExperience,
        public ?string $availability,
        public ?string $bio,
    ) {}

    public static function forUser(User $mentor): self
    {
        $profile = $mentor->mentorProfile;

        return new self(
            id: $mentor->id,
            name: $mentor->name,
            expertise: $profile?->primary_expertise,
            industries: $profile?->industry_focus ?? [],
            yearsExperience: $profile?->years_experience,
            availability: $profile?->availability,
            bio: $profile?->afcfta_knowledge,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'expertise' => $this->expertise,
            'industries' => $this->industries,
            'yearsExperience' => $this->yearsExperience,
            'availability' => $this->availability,
            'bio' => $this->bio,
        ];
    }
}
