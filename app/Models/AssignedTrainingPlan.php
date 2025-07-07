<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignedTrainingPlan extends Model
{
    protected $fillable = ['athlete_id', 'training_plan_id', 'start_date', 'is_customized'];

    /**
     * Get the athlete that owns the AssignedTrainingPlan.
     */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    /**
     * Get the training plan that owns the AssignedTrainingPlan.
     */
    public function trainingPlan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class);
    }

    /**
     * Getter for athlete_id attribute
     *
     * @return int
     */
    public function getAthleteId(): int
    {
        return $this->athlete_id;
    }

    /**
     * Setter for athlete_id attribute
     *
     * @param int $athleteId
     * @return void
     */
    public function setAthleteId(int $athleteId): void
    {
        $this->athlete_id = $athleteId;
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
     * Getter for start_date attribute
     *
     * @return \Illuminate\Support\Carbon
     */
    public function getStartDate(): \Illuminate\Support\Carbon
    {
        return $this->start_date;
    }

    /**
     * Setter for start_date attribute
     *
     * @param \Illuminate\Support\Carbon $startDate
     * @return void
     */
    public function setStartDate(\Illuminate\Support\Carbon $startDate): void
    {
        $this->start_date = $startDate;
    }

    /**
     * Getter for is_customized attribute
     *
     * @return bool
     */
    public function getIsCustomized(): bool
    {
        return $this->is_customized;
    }

    /**
     * Setter for is_customized attribute
     *
     * @param bool $isCustomized
     * @return void
     */
    public function setIsCustomized(bool $isCustomized): void
    {
        $this->is_customized = $isCustomized;
    }
}