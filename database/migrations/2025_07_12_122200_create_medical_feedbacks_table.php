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
        Schema::create('medical_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('injury_id')->nullable()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('feedback_date')->nullable();
            $table->string('professional_type')->nullable(); // ProfessionalType
            $table->text('diagnosis')->nullable();
            $table->text('treatment_plan')->nullable();
            $table->text('rehab_progress')->nullable();
            $table->text('training_limitations')->nullable();
            $table->date('next_appointment_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('reported_by_athlete')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_feedbacks');
    }
};