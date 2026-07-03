<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\UserInvitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    /**
     * Only an approved admin may issue admin/mentor/entrepreneur invitations.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', UserInvitation::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email'),
                Rule::unique('user_invitations', 'email')->where(
                    fn ($query) => $query
                        ->where('role', $this->input('role'))
                        ->whereNull('accepted_at')
                        ->whereNull('revoked_at')
                        ->where('expires_at', '>', now())
                ),
            ],
            'role' => ['required', Rule::enum(UserRole::class)->only(UserRole::invitable())],
            'name' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ];
    }
}
