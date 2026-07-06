<?php

namespace App\Models;

use App\Enums\PairingStatus;
use Database\Factories\PairingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pairing extends Model
{
    /** @use HasFactory<PairingFactory> */
    use HasFactory;

    protected $fillable = [
        'entrepreneur_user_id',
        'mentor_user_id',
        'status',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PairingStatus::class,
            'ended_at' => 'datetime',
        ];
    }

    public function entrepreneur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entrepreneur_user_id');
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_user_id');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }
}
