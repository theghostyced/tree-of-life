<?php

namespace App\Models;

use Database\Factories\MentorProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property array<int, string>|null $expertise_areas
 * @property array<int, string>|null $industry_focus
 * @property int|null $years_experience
 * @property string|null $afcfta_knowledge
 * @property string|null $availability
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id', 'expertise_areas', 'industry_focus', 'years_experience',
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
            'expertise_areas' => 'array',
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
