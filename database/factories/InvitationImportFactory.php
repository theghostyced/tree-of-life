<?php

namespace Database\Factories;

use App\Enums\InvitationImportStatus;
use App\Models\InvitationImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvitationImport>
 */
class InvitationImportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'imported_by' => User::factory()->admin()->approved(),
            'filename' => 'invitations.csv',
            'status' => InvitationImportStatus::Pending,
            'total_rows' => 0,
            'invited_count' => 0,
            'skipped_count' => 0,
            'invalid_count' => 0,
            'row_errors' => [],
        ];
    }
}
