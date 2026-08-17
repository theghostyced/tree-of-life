<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether an invitation's email actually got out is separate from its
     * lifecycle: a pending invitation whose mail never left is invisible
     * otherwise, because the row looks identical to one sitting in an inbox.
     */
    public function up(): void
    {
        Schema::table('user_invitations', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('revoked_at');
            $table->timestamp('delivery_failed_at')->nullable()->after('delivered_at');
            $table->text('delivery_error')->nullable()->after('delivery_failed_at');

            // Admins filter for "what never went out"; that is the whole point
            // of the column, so it is worth an index from the start.
            $table->index('delivery_failed_at');
        });

        // Every invitation that predates this migration was created by the old
        // path, which threw on failure. Anything already stored therefore got
        // as far as the queue, so treat it as delivered rather than unknown.
        DB::table('user_invitations')->whereNull('delivered_at')->update([
            'delivered_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('user_invitations', function (Blueprint $table) {
            $table->dropIndex(['delivery_failed_at']);
            $table->dropColumn(['delivered_at', 'delivery_failed_at', 'delivery_error']);
        });
    }
};
