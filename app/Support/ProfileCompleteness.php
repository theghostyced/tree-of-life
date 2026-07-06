<?php

namespace App\Support;

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\User;

/**
 * Computes how much of a role's onboarding profile is filled in. This is a pure
 * read-only calculation: the invitation is the approval, so completeness only
 * drives the dashboard nudge and feature-readiness — it never changes status.
 */
class ProfileCompleteness
{
    /**
     * Total number of items (fields + required documents) a role must supply.
     */
    public function requiredCount(User $user): int
    {
        return match ($user->role) {
            UserRole::Entrepreneur => 7 + count(DocumentType::requiredFor(UserRole::Entrepreneur)),
            UserRole::Mentor => 5 + count(DocumentType::requiredFor(UserRole::Mentor)),
            default => 0,
        };
    }

    /**
     * Human-readable list of what the user still needs to provide.
     *
     * @return array<int, string>
     */
    public function missingItems(User $user): array
    {
        $missing = [];

        if ($user->role === UserRole::Entrepreneur) {
            $profile = $user->entrepreneurProfile;
            $fields = ['business_name', 'business_description', 'business_email', 'business_phone_number', 'years_in_operation', 'employee_count'];
            foreach ($fields as $field) {
                if (blank($profile?->{$field})) {
                    $missing[] = 'Add your '.str_replace('_', ' ', $field).'.';
                }
            }
            if (blank($profile?->sector)) {
                $missing[] = 'Select at least one business sector.';
            }
        } elseif ($user->role === UserRole::Mentor) {
            $profile = $user->mentorProfile;
            $fields = ['primary_expertise', 'years_experience', 'afcfta_knowledge', 'availability'];
            foreach ($fields as $field) {
                if (blank($profile?->{$field})) {
                    $missing[] = 'Add your '.str_replace('_', ' ', $field).'.';
                }
            }
            if (blank($profile?->industry_focus)) {
                $missing[] = 'Select at least one industry focus.';
            }
        }

        foreach (DocumentType::requiredFor($user->role) as $type) {
            if (! $user->documents()->where('document_type', $type)->exists()) {
                $missing[] = 'Upload '.str_replace('_', ' ', $type->value).'.';
            }
        }

        return $missing;
    }
}
