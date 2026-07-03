<?php

namespace App\Models;

use Database\Factories\EntrepreneurProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'business_name', 'business_description', 'business_email',
    'business_phone_number', 'sector', 'years_in_operation', 'employee_count',
])]
class EntrepreneurProfile extends Model
{
    /** @use HasFactory<EntrepreneurProfileFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sector' => 'array',
            'years_in_operation' => 'integer',
            'employee_count' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
