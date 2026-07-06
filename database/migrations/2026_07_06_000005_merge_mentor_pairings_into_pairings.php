<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The mentor-selection flow briefly wrote to its own mentor_pairings
     * table while the mentorship domain read pairings. Converge: copy any
     * rows across as active pairings, then retire the fork table.
     */
    public function up(): void
    {
        if (! Schema::hasTable('mentor_pairings')) {
            return;
        }

        foreach (DB::table('mentor_pairings')->get() as $row) {
            $exists = DB::table('pairings')
                ->where('entrepreneur_user_id', $row->entrepreneur_id)
                ->where('mentor_user_id', $row->mentor_id)
                ->where('status', 'active')
                ->exists();

            if (! $exists) {
                DB::table('pairings')->insert([
                    'entrepreneur_user_id' => $row->entrepreneur_id,
                    'mentor_user_id' => $row->mentor_id,
                    'status' => 'active',
                    'ended_at' => null,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }
        }

        Schema::dropIfExists('mentor_pairings');
    }

    public function down(): void
    {
        // One way by design: recreating the fork table would resurrect the
        // split-brain pairing state this migration exists to remove.
    }
};
