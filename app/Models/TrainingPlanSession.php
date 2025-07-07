<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingPlanSession extends Model
{
    protected $fillable = ['training_plan_day_id', 'session_type', 'volume_planned', 'intensity_planned'];

    /**
     * Get the training plan day that owns the TrainingPlanSession.
     */
    public function day(): BelongsTo
    {
        return $this->belongsTo(TrainingPlanDay::class);
    }

    /**
     * Getter for training_plan_day_id attribute
     *
     * @return int
     */
    public function getTrainingPlanDayId(): int
    {
        return $this->training_plan_day_id;
    }

    /**
     * Setter for training_plan_day_id attribute
     *
     * @param int $trainingPlanDayId
     * @return void
     */
    public function setTrainingPlanDayId(int $trainingPlanDayId): void
    {
        $this->training_plan_day_id = $trainingPlanDayId;
    }

    /**
     * Getter for session_type attribute
     *
     * @return string
     */
    public function getSessionType(): string
    {
        return $this->session_type;
    }

    /**
     * Setter for session_type attribute
     *
     * @param string $sessionType
     * @return void
     */
    public function setSessionType(string $sessionType): void
    {
        $this->session_type = $sessionType;
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