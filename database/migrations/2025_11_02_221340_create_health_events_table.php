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
        Schema::create('health_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->nullable()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('injury_id')->nullable()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('professional_id')->nullable()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('date')->nullable();
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->text('purpose')->nullable();
            $table->text('summary_notes')->nullable();
            $table->text('recommendations')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->integer('effect_on_pain_intensity')->nullable();
            $table->integer('effectiveness_rating')->nullable();
            $table->boolean('reported_by_athlete')->default(false);
            $table->boolean('is_private')->default(false);
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_events');
    }
};
