<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingPlanWeek extends Model
{
    protected $fillable = ['training_plan_id', 'week_number', 'volume_planned', 'intensity_planned'];

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
     *
     * @return int
     */
    public function getTrainingPlanId(): int
    {
        return $this->training_plan_id;
    }

    /**
     * Setter for training_plan_id attribute
     *
     * @param int $trainingPlanId
     * @return void
     */
    public function setTrainingPlanId(int $trainingPlanId): void
    {
        $this->training_plan_id = $trainingPlanId;
    }

    /**
     * Getter for week_number attribute
     *
     * @return int
     */
    public function getWeekNumber(): int
    {
        return $this->week_number;
    }

    /**
     * Setter for week_number attribute
     *
     * @param int $weekNumber
     * @return void
     */
    public function setWeekNumber(int $weekNumber): void
    {
        $this->week_number = $weekNumber;
    }

    /**
     * Getter for volume_planned attribute
     *
     * @return int
     */
    public function getVolumePlanned(): int
    {
        return $this->volume_planned;
    }

    /**
     * Setter for volume_planned attribute
     *
     * @param int $volumePlanned
     * @return void
     */
    public function setVolumePlanned(int $volumePlanned): void
    {
        $this->volume_planned = $volumePlanned;
    }

    /**
     * Getter for intensity_planned attribute
     *
     * @return int
     */
    public function getIntensityPlanned(): int
    {
        return $this->intensity_planned;
    }

    /**
     * Setter for intensity_planned attribute
     *
     * @param int $intensityPlanned
     * @return void
     */
    public function setIntensityPlanned(int $intensityPlanned): void
    {
        $this->intensity_planned = $intensityPlanned;
    }
}