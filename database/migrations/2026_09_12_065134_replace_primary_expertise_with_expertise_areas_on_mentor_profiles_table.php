<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_profiles', function (Blueprint $table) {
            $table->json('expertise_areas')->nullable()->after('user_id');
        });

        DB::table('mentor_profiles')
            ->whereNotNull('primary_expertise')
            ->orderBy('id')
            ->each(fn (object $profile) => DB::table('mentor_profiles')
                ->where('id', $profile->id)
                ->update(['expertise_areas' => json_encode([$profile->primary_expertise])]));

        Schema::table('mentor_profiles', function (Blueprint $table) {
            $table->dropColumn('primary_expertise');
        });
    }

    public function down(): void
    {
        Schema::table('mentor_profiles', function (Blueprint $table) {
            $table->string('primary_expertise')->nullable()->after('user_id');
        });

        DB::table('mentor_profiles')
            ->whereNotNull('expertise_areas')
            ->orderBy('id')
            ->each(fn (object $profile) => DB::table('mentor_profiles')
                ->where('id', $profile->id)
                ->update(['primary_expertise' => json_decode($profile->expertise_areas, true)[0] ?? null]));

        Schema::table('mentor_profiles', function (Blueprint $table) {
            $table->dropColumn('expertise_areas');
        });
    }
};
