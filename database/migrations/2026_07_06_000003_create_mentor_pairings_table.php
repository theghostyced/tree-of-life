<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_pairings', function (Blueprint $table) {
            $table->id();
            // One active mentor per entrepreneur; a mentor may have many mentees.
            $table->foreignId('entrepreneur_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_pairings');
    }
};
