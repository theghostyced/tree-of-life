<?php

namespace App\Models;

use App\Enums\InvitationImportStatus;
use Database\Factories\InvitationImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationImport extends Model
{
    /** @use HasFactory<InvitationImportFactory> */
    use HasFactory;

    /**
     * The skipped/invalid report stops growing at this many entries;
     * the counts keep incrementing past it.
     */
    public const MAX_ROW_ERRORS = 1000;

    protected $fillable = [
        'imported_by',
        'filename',
        'status',
        'total_rows',
        'invited_count',
        'skipped_count',
        'invalid_count',
        'row_errors',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvitationImportStatus::class,
            'row_errors' => 'array',
        ];
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /**
     * Where the uploaded CSV lives on the private local disk until the job finishes.
     */
    public function storagePath(): string
    {
        return "invitation-imports/{$this->id}.csv";
    }
}
