<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Role / status counts (stats + readiness) and month buckets (growth)
            // on the admin dashboard aggregate over these columns.
            $table->index(['role', 'account_status'], 'users_role_status_index');
            $table->index('created_at', 'users_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_status_index');
            $table->dropIndex('users_created_at_index');
        });
    }
};
