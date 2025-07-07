<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTrainingPlanSessionsTable extends Migration
{
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

    public function down(): void
    {
        Schema::dropIfExists('training_plan_sessions');
    }
}