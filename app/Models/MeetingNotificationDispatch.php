<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A ledger row recording that a given time based reminder (kind) has been sent
 * for a meeting, so the every few minutes scan never sends it twice.
 */
class MeetingNotificationDispatch extends Model
{
    public $timestamps = false;

    protected $fillable = ['meeting_id', 'kind', 'dispatched_at'];

    protected function casts(): array
    {
        return ['dispatched_at' => 'datetime'];
    }
}
