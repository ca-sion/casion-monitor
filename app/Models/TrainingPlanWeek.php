<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrainingPlanWeek extends Model
{

    /** @use HasFactory<\Database\Factories\TrainingPlanFactory> */
    use HasFactory;
    
    protected $fillable = ['training_plan_id', 'week_number', 'start_date', 'volume_planned', 'intensity_planned'];

    /**
     * Get the training plan that owns the TrainingPlanWeek.
     */
    public function trainingPlan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class);
    }

    /**
     * Get the days for the TrainingPlanWeek.
     */
    public function days(): HasMany
    {
        return $this->hasMany(TrainingPlanDay::class);
    }

    /**
     * Getter for training_plan_id attribute
     */
    public function getTrainingPlanId(): int
    {
        return $this->training_plan_id;
    }

    /**
     * Setter for training_plan_id attribute
     */
    public function setTrainingPlanId(int $trainingPlanId): void
    {
        $this->training_plan_id = $trainingPlanId;
    }

    /**
     * Getter for week_number attribute
     */
    public function getWeekNumber(): int
    {
        return $this->week_number;
    }

    /**
     * Setter for week_number attribute
     */
    public function setWeekNumber(int $weekNumber): void
    {
        $this->week_number = $weekNumber;
    }

    /**
     * Getter for volume_planned attribute
     */
    public function getVolumePlanned(): int
    {
        return $this->volume_planned;
    }

    /**
     * Setter for volume_planned attribute
     */
    public function setVolumePlanned(int $volumePlanned): void
    {
        $this->volume_planned = $volumePlanned;
    }

    /**
     * Getter for intensity_planned attribute
     */
    public function getIntensityPlanned(): int
    {
        return $this->intensity_planned;
    }

    /**
     * Setter for intensity_planned attribute
     */
    public function setIntensityPlanned(int $intensityPlanned): void
    {
        $this->intensity_planned = $intensityPlanned;
    }
}
