<?php

namespace App\Http\Requests\Entrepreneur;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeInvitationRequest extends FormRequest
{
    /**
     * Only an approved entrepreneur may invite employees. The employee role is
     * forced by the controller; it is never taken from the request payload.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isEntrepreneur() && $user->isApproved();
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
                        ->whereNull('accepted_at')
                        ->whereNull('revoked_at')
                        ->where('expires_at', '>', now())
                ),
            ],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
