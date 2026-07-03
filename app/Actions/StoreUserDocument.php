<?php

namespace App\Actions;

use App\Enums\DocumentType;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StoreUserDocument
{
    private const DISK = 'local';

    /**
     * Store an onboarding document on the private disk. Re-uploading a document
     * of the same type replaces the previous file (the old file is deleted) so
     * there is only ever one current file per required type.
     */
    public function handle(User $user, DocumentType $type, UploadedFile $file): UserDocument
    {
        $existing = $user->documents()
            ->where('document_type', $type)
            ->first();

        if ($existing !== null) {
            Storage::disk($existing->disk)->delete($existing->path);
        }

        $path = $file->store("user-documents/{$user->id}", self::DISK);

        return UserDocument::updateOrCreate(
            ['user_id' => $user->id, 'document_type' => $type],
            [
                'disk' => self::DISK,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_at' => now(),
            ],
        );
    }
}
