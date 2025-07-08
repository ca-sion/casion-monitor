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
        Schema::create('training_plan_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_plan_day_id')->nullable()->constrained('training_plan_days')->cascadeOnDelete();
            $table->string('session_type')->nullable();
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
        Schema::dropIfExists('training_plan_sessions');
    }
};
