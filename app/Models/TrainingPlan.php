<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrainingPlan extends Model
{

    /** @use HasFactory<\Database\Factories\TrainingPlanFactory> */
    use HasFactory;
    
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
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Setter for name attribute
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Getter for description attribute
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Setter for description attribute
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * Getter for trainer_id attribute
     */
    public function getTrainerId(): int
    {
        return $this->trainer_id;
    }

    /**
     * Setter for trainer_id attribute
     */
    public function setTrainerId(int $trainerId): void
    {
        $this->trainer_id = $trainerId;
    }
}
