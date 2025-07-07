<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingPlan extends Model
{
    protected $fillable = ['name', 'description', 'trainer_id'];

    /**
     * Get the trainer that owns the TrainingPlan.
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    /**
     * Get the weeks for the TrainingPlan.
     */
    public function weeks(): HasMany
    {
        return $this->hasMany(TrainingPlanWeek::class);
    }

    /**
     * Get the assigned training plans for the TrainingPlan.
     */
    public function assignedTrainingPlans(): HasMany
    {
        return $this->hasMany(AssignedTrainingPlan::class);
    }

    /**
     * Getter for name attribute
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Setter for name attribute
     *
     * @param string $name
     * @return void
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Getter for description attribute
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Setter for description attribute
     *
     * @param string $description
     * @return void
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * Getter for trainer_id attribute
     *
     * @return int
     */
    public function getTrainerId(): int
    {
        return $this->trainer_id;
    }

    /**
     * Setter for trainer_id attribute
     *
     * @param int $trainerId
     * @return void
     */
    public function setTrainerId(int $trainerId): void
    {
        $this->trainer_id = $trainerId;
    }
}