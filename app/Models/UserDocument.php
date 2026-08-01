<?php

namespace App\Models;

use App\Enums\DocumentType;
use Database\Factories\UserDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property DocumentType $document_type
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 */
#[Fillable([
    'user_id', 'document_type', 'disk', 'path', 'original_name', 'mime_type', 'size', 'uploaded_at',
])]
class UserDocument extends Model
{
    /** @use HasFactory<UserDocumentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'uploaded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether a browser can safely render this file inline in an iframe.
     *
     * An allowlist, not an `image/*` prefix: that would admit image/svg+xml,
     * which can run script on our own origin.
     */
    public function isPreviewable(): bool
    {
        return in_array($this->mime_type, [
            'application/pdf',
            'image/png',
            'image/jpeg',
            'image/gif',
            'image/webp',
        ], true);
    }
}
