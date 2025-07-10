<?php

namespace App\Models;

use App\Enums\CalculatedMetric;
use Illuminate\Database\Eloquent\Model;
use App\Services\MetricStatisticsService;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
     * Get the training plan week's cph.
     */
    protected function cph(): Attribute
    {
        return Attribute::make(
            get: fn () => resolve(MetricStatisticsService::class)->calculateCph($this),
        );
    }

    /**
     * Get the training plan week's cph normalized over ten scale.
     */
    protected function cphNormalizedOverTen(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->cph * 10 / CalculatedMetric::CPH->getScale(),
        );
    }
}
