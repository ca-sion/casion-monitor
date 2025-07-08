<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAssignedTrainingPlansTable extends Migration
{
    public function up(): void
    {
        Schema::create('assigned_training_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->nullable()->constrained('athletes')->cascadeOnDelete();
            $table->foreignId('training_plan_id')->nullable()->constrained('training_plans')->cascadeOnDelete();
            $table->date('start_date')->nullable();
            $table->boolean('is_customized')->nullable()->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assigned_training_plans');
    }
}
