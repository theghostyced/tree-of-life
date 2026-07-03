<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrepreneur_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name')->nullable();
            $table->text('business_description')->nullable();
            $table->string('business_email')->nullable()->unique();
            $table->string('business_phone_number')->nullable()->unique();
            $table->json('sector')->nullable();
            $table->unsignedInteger('years_in_operation')->nullable();
            $table->unsignedInteger('employee_count')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrepreneur_profiles');
    }
};
