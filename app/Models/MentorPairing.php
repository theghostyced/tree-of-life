<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorPairing extends Model
{
    protected $fillable = ['entrepreneur_id', 'mentor_id'];

    /** @return BelongsTo<User, $this> */
    public function entrepreneur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entrepreneur_id');
    }

    /** @return BelongsTo<User, $this> */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }
}
