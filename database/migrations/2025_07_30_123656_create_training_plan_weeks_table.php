<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTrainingPlanWeeksTable extends Migration
{
    public function up(): void
    {
        Schema::create('training_plan_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_plan_id')->nullable()->constrained('training_plans')->cascadeOnDelete();
            $table->integer('week_number')->nullable();
            $table->integer('volume_planned')->nullable();
            $table->integer('intensity_planned')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_plan_weeks');
    }
}
