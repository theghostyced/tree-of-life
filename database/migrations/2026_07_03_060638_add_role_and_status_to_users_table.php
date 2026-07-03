<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('entrepreneur')->after('email');
            $table->string('account_status')->default('draft')->after('role');
            $table->string('phone_number')->nullable()->unique()->after('account_status');
            $table->unsignedBigInteger('company_id')->nullable()->after('phone_number');
            $table->timestamp('account_status_changed_at')->nullable();
            $table->timestamp('profile_submitted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role', 'account_status', 'phone_number', 'company_id',
                'account_status_changed_at', 'profile_submitted_at',
            ]);
        });
    }
};
