<?php

namespace App\Models;

use Database\Factories\MentorAvailabilitySlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorAvailabilitySlot extends Model
{
    /** @use HasFactory<MentorAvailabilitySlotFactory> */
    use HasFactory;

    protected $fillable = [
        'mentor_user_id',
        'day_of_week',
        'start_time',
        'end_time',
        'timezone',
        'session_type',
        'location',
        'meeting_link',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_user_id');
    }
}
