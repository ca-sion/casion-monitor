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
        Schema::create('recovery_protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->nullable()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('date')->nullable();
            $table->string('recovery_type')->nullable(); // RecoveryType
            $table->integer('duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('related_injury_id')->nullable()->constrained('injuries')->cascadeOnUpdate()->cascadeOnDelete();
            $table->integer('effect_on_pain_intensity')->nullable();
            $table->integer('effectiveness_rating')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recovery_protocols');
    }
};