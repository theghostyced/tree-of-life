<?php

namespace App\Http\Requests\Admin;

use App\Models\UserInvitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkInvitationActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', UserInvitation::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['resend', 'revoke'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:user_invitations,id'],
        ];
    }
}
