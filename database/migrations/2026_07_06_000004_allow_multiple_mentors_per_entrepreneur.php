<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Was: one mentor per entrepreneur. Now: many mentors, but the same
        // pairing can't be created twice.
        //
        // The composite is added before the old index is dropped because
        // InnoDB refuses to drop the last index serving entrepreneur_id's
        // foreign key. It accepts the composite in its place: the FK column
        // is that index's leftmost prefix.
        Schema::table('mentor_pairings', function (Blueprint $table) {
            $table->unique(['entrepreneur_id', 'mentor_id']);
        });

        Schema::table('mentor_pairings', function (Blueprint $table) {
            $table->dropUnique('mentor_pairings_entrepreneur_id_unique');
        });
    }

    public function down(): void
    {
        // A later migration retires this table for good, so rolling back
        // that far leaves nothing to restore.
        if (! Schema::hasTable('mentor_pairings')) {
            return;
        }

        Schema::table('mentor_pairings', function (Blueprint $table) {
            $table->unique('entrepreneur_id');
        });

        Schema::table('mentor_pairings', function (Blueprint $table) {
            $table->dropUnique(['entrepreneur_id', 'mentor_id']);
        });
    }
};
