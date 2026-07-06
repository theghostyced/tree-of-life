<?php

namespace App\Http\Requests\Admin;

use App\Jobs\ProcessInvitationImport;
use App\Models\UserInvitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInvitationImportRequest extends FormRequest
{
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
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }

    /**
     * The header must match the template exactly, and at least one data row
     * must follow it.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('file') || ! $this->file('file')) {
                    return;
                }

                $stream = fopen($this->file('file')->getRealPath(), 'rb');
                $header = fgetcsv($stream, escape: '');
                $hasDataRow = false;

                while (($cells = fgetcsv($stream, escape: '')) !== false) {
                    if (! ProcessInvitationImport::isBlankRow($cells)) {
                        $hasDataRow = true;
                        break;
                    }
                }

                fclose($stream);

                $normalized = array_map(
                    fn ($cell) => strtolower(trim((string) $cell)),
                    $header ?: [],
                );

                if ($normalized !== ['email', 'role', 'name']) {
                    $validator->errors()->add('file', 'The first line must be the template header: email,role,name.');
                } elseif (! $hasDataRow) {
                    $validator->errors()->add('file', 'The file has no data rows below the header.');
                }
            },
        ];
    }
}
