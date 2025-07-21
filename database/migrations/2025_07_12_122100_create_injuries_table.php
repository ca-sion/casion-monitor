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
        Schema::create('injuries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->nullable()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('declaration_date')->nullable();
            $table->string('status')->nullable(); // njuryStatus
            $table->integer('pain_intensity')->nullable();
            $table->string('pain_location')->nullable();
            $table->string('injury_type')->nullable(); // InjuryType
            $table->text('onset_circumstances')->nullable();
            $table->text('impact_on_training')->nullable();
            $table->text('description')->nullable();
            $table->text('athlete_diagnosis_feeling')->nullable();
            $table->date('resolved_date')->nullable();
            $table->boolean('session_related')->default(false);
            $table->date('session_date')->nullable();
            $table->string('session_type')->nullable();
            $table->boolean('immediate_onset')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('injuries');
    }
};
