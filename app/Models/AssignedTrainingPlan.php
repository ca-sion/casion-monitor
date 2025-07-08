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
     */
    public function getAthleteId(): int
    {
        return $this->athlete_id;
    }

    /**
     * Setter for athlete_id attribute
     */
    public function setAthleteId(int $athleteId): void
    {
        $this->athlete_id = $athleteId;
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
     * Getter for start_date attribute
     */
    public function getStartDate(): \Illuminate\Support\Carbon
    {
        return $this->start_date;
    }

    /**
     * Setter for start_date attribute
     */
    public function setStartDate(\Illuminate\Support\Carbon $startDate): void
    {
        $this->start_date = $startDate;
    }

    /**
     * Getter for is_customized attribute
     */
    public function getIsCustomized(): bool
    {
        return $this->is_customized;
    }

    /**
     * Setter for is_customized attribute
     */
    public function setIsCustomized(bool $isCustomized): void
    {
        $this->is_customized = $isCustomized;
    }
}
