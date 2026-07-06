<?php

namespace App\Http\Requests\Mentor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAvailabilitySlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isMentor() && $this->user()->isApproved();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // 0 = Monday .. 6 = Sunday
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'session_type' => ['required', Rule::in(['virtual', 'in_person'])],
            'location' => ['nullable', 'required_if:session_type,in_person', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'url', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // In-person slots carry no link; virtual slots carry no physical location.
        if ($this->input('session_type') === 'virtual') {
            $this->merge(['location' => null]);
        } else {
            $this->merge(['meeting_link' => null]);
        }
    }
}
