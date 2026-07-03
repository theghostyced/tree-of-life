<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * The document type must belong to the user's role, and the file
     * constraints (allowed extensions, max size) come from that type.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $allowed = array_map(
            fn (DocumentType $type): string => $type->value,
            DocumentType::requiredFor($this->user()->role),
        );

        $type = DocumentType::tryFrom((string) $this->input('document_type'));

        $fileRule = $type !== null
            ? ['required', File::types($type->allowedExtensions())->max($type->maxKilobytes())]
            : ['required', 'file'];

        return [
            'document_type' => ['required', Rule::in($allowed)],
            'file' => $fileRule,
        ];
    }
}
