<?php

namespace App\Data;

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class MentorOnboardingData implements Arrayable
{
    /**
     * @param  array<int, DocumentRequirement>  $requiredDocuments
     */
    public function __construct(
        public MentorProfileFields $profile,
        public array $requiredDocuments,
        public OnboardingProgress $progress,
    ) {}

    public static function forUser(User $user): self
    {
        $documents = $user->documents->keyBy(fn (UserDocument $doc) => $doc->document_type->value);

        $requiredDocuments = collect(DocumentType::requiredFor(UserRole::Mentor))
            ->map(fn (DocumentType $type): DocumentRequirement => new DocumentRequirement(
                type: $type->value,
                label: $type->label(),
                uploaded: $documents->get($type->value)?->original_name,
            ))->all();

        return new self(
            profile: MentorProfileFields::fromProfile($user->mentorProfile),
            requiredDocuments: $requiredDocuments,
            progress: OnboardingProgress::forUser($user),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'profile' => $this->profile->toArray(),
            'requiredDocuments' => array_map(
                fn (DocumentRequirement $doc): array => $doc->toArray(),
                $this->requiredDocuments,
            ),
            'progress' => $this->progress->toArray(),
        ];
    }
}
