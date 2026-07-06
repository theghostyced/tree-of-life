<?php

namespace App\Models;

use App\Enums\RescheduleStatus;
use Database\Factories\MeetingRescheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingReschedule extends Model
{
    /** @use HasFactory<MeetingRescheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'requested_by_user_id',
        'status',
        'reason',
        'previous_starts_at',
        'previous_ends_at',
        'new_starts_at',
        'new_ends_at',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RescheduleStatus::class,
            'previous_starts_at' => 'datetime',
            'previous_ends_at' => 'datetime',
            'new_starts_at' => 'datetime',
            'new_ends_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
