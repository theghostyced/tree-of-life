<?php

namespace App\Http\Requests\Mentor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMentorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isMentor() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'primary_expertise' => ['nullable', 'string', 'max:255'],
            'industry_focus' => ['nullable', 'array'],
            'industry_focus.*' => ['string', 'max:255'],
            'years_experience' => ['nullable', 'integer', 'min:0'],
            'afcfta_knowledge' => ['nullable', 'string'],
            'availability' => ['nullable', 'string', 'max:255'],
        ];
    }
}
