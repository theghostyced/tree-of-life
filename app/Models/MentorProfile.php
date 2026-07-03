<?php

namespace App\Models;

use Database\Factories\MentorProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'primary_expertise', 'industry_focus', 'years_experience',
    'afcfta_knowledge', 'availability',
])]
class MentorProfile extends Model
{
    /** @use HasFactory<MentorProfileFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'industry_focus' => 'array',
            'years_experience' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
