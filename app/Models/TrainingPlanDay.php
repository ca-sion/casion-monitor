<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingPlanDay extends Model
{
    protected $fillable = ['training_plan_week_id', 'day_of_week', 'volume_planned', 'intensity_planned'];

    /**
     * Get the training plan week that owns the TrainingPlanDay.
     */
    public function week(): BelongsTo
    {
        return $this->belongsTo(TrainingPlanWeek::class, 'training_plan_week_id');
    }

    /**
     * Get the sessions for the TrainingPlanDay.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingPlanSession::class);
    }

    /**
     * Getter for training_plan_week_id attribute
     *
     * @return int
     */
    public function getTrainingPlanWeekId(): int
    {
        return $this->training_plan_week_id;
    }

    /**
     * Setter for training_plan_week_id attribute
     *
     * @param int $trainingPlanWeekId
     * @return void
     */
    public function setTrainingPlanWeekId(int $trainingPlanWeekId): void
    {
        $this->training_plan_week_id = $trainingPlanWeekId;
    }

    /**
     * Getter for day_of_week attribute
     *
     * @return int
     */
    public function getDayOfWeek(): int
    {
        return $this->day_of_week;
    }

    /**
     * Setter for day_of_week attribute
     *
     * @param int $dayOfWeek
     * @return void
     */
    public function setDayOfWeek(int $dayOfWeek): void
    {
        $this->day_of_week = $dayOfWeek;
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