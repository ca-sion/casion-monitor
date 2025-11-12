<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TrainingPlan extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingPlanFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'trainer_id', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }

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
     * The athletes that are assigned to the TrainingPlan.
     */
    public function athletes(): BelongsToMany
    {
        return $this->belongsToMany(Athlete::class, 'assigned_training_plans', 'training_plan_id', 'athlete_id')
            ->withPivot('start_date', 'is_customized');
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
