<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_pairings', function (Blueprint $table) {
            // Was: one mentor per entrepreneur. Now: many mentors, but the same
            // pairing can't be created twice.
            $table->dropUnique('mentor_pairings_entrepreneur_id_unique');
            $table->unique(['entrepreneur_id', 'mentor_id']);
        });
    }

    public function down(): void
    {
        Schema::table('mentor_pairings', function (Blueprint $table) {
            $table->dropUnique(['entrepreneur_id', 'mentor_id']);
            $table->unique('entrepreneur_id');
        });
    }
};
