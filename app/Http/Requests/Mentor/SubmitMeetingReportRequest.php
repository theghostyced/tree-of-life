<?php

namespace App\Http\Requests\Mentor;

use Illuminate\Foundation\Http\FormRequest;

class SubmitMeetingReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $meeting = $this->route('meeting');

        return $this->user()?->can('submitReport', $meeting) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'summary' => ['required', 'string', 'max:5000'],
        ];
    }
}
