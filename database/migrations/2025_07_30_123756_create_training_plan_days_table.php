<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('training_plan_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_plan_week_id')->nullable()->constrained('training_plan_weeks')->cascadeOnDelete();
            $table->integer('day_of_week')->nullable(); // 1 for Monday, 7 for Sunday
            $table->integer('volume_planned')->nullable();
            $table->integer('intensity_planned')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_plan_days');
    }
};
