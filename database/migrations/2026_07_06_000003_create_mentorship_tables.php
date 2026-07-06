<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pairings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrepreneur_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['entrepreneur_user_id', 'status']);
            $table->index(['mentor_user_id', 'status']);
        });

        Schema::create('mentor_availability_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentor_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 = Monday .. 6 = Sunday
            $table->time('start_time');
            $table->time('end_time');
            $table->string('timezone');
            $table->string('session_type'); // virtual | in_person
            $table->string('location')->nullable();
            $table->string('meeting_link')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['mentor_user_id', 'is_active']);
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pairing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mentor_availability_slot_id')->nullable()->constrained('mentor_availability_slots')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone');
            $table->string('session_type');
            $table->string('location')->nullable();
            // The Google Meet URL once the booking integration lands.
            $table->string('meeting_link')->nullable();
            // Calendar event reference; unused until the booking spec.
            $table->string('google_event_id')->nullable();
            $table->text('agenda')->nullable();
            $table->string('status')->default('confirmed');
            $table->text('outcome_summary')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pairing_id', 'status']);
            $table->index(['status', 'starts_at']);
        });

        Schema::create('meeting_reschedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('previous_starts_at');
            $table->timestamp('previous_ends_at');
            $table->timestamp('new_starts_at');
            $table->timestamp('new_ends_at');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['meeting_id', 'status']);
        });

        Schema::create('meeting_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('summary');
            $table->timestamp('submitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_reports');
        Schema::dropIfExists('meeting_reschedules');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('mentor_availability_slots');
        Schema::dropIfExists('pairings');
    }
};
