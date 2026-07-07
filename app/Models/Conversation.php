<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    protected $fillable = ['pairing_id', 'last_message_at', 'last_message_preview', 'last_message_sender_id'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function pairing(): BelongsTo
    {
        return $this->belongsTo(Pairing::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function participantFor(User $user): ?ConversationParticipant
    {
        return $this->participants()->where('user_id', $user->id)->first();
    }

    public function otherParticipant(User $user): ?ConversationParticipant
    {
        return $this->participants()->where('user_id', '!=', $user->id)->first();
    }

    public function isActive(): bool
    {
        return $this->pairing->ended_at === null;
    }

    public function unreadCountFor(User $user): int
    {
        $participant = $this->participantFor($user);

        return $this->messages()
            ->where(function ($q) use ($user) {
                $q->whereNull('sender_user_id')->orWhere('sender_user_id', '!=', $user->id);
            })
            ->when($participant?->last_read_message_id, fn ($q, $id) => $q->where('id', '>', $id))
            ->count();
    }
}
