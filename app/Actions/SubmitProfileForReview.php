<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SubmitProfileForReview
{
    /**
     * Submit a completed profile for admin review. Throws a validation error
     * listing what is still missing; on success moves draft/rejected -> pending.
     */
    public function handle(User $user): void
    {
        $missing = $this->missingItems($user);

        if ($missing !== []) {
            throw ValidationException::withMessages(['profile' => $missing]);
        }

        $user->forceFill([
            'account_status' => AccountStatus::Pending,
            'profile_submitted_at' => now(),
            'account_status_changed_at' => now(),
        ])->save();
    }

    /**
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
