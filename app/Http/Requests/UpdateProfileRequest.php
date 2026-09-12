<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Email is deliberately absent: it is bound to the invitation and only an
     * admin may change it.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'avatar' => [
                'nullable',
                File::image()->types(['jpg', 'jpeg', 'png'])->max(2048),
                Rule::dimensions()->minWidth(128)->minHeight(128),
            ],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }
}
