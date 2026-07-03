<?php

namespace App\Http\Requests\Entrepreneur;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEntrepreneurProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isEntrepreneur() ?? false;
    }

    /**
     * Every field is optional so a draft profile can be saved incrementally;
     * completeness is enforced at submission time, not here.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $profileId = $this->user()->entrepreneurProfile?->id;

        return [
            'business_name' => ['nullable', 'string', 'max:255'],
            'business_description' => ['nullable', 'string'],
            'business_email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('entrepreneur_profiles', 'business_email')->ignore($profileId),
            ],
            'business_phone_number' => [
                'nullable', 'string', 'max:20',
                Rule::unique('entrepreneur_profiles', 'business_phone_number')->ignore($profileId),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && $value === $this->user()->phone_number) {
                        $fail('Your business phone number must differ from your personal phone number.');
                    }
                },
            ],
            'sector' => ['nullable', 'array'],
            'sector.*' => ['string', 'max:255'],
            'years_in_operation' => ['nullable', 'integer', 'min:0'],
            'employee_count' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
