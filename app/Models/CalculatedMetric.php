<?php

namespace App\Models;

use App\Enums\CalculatedMetricType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CalculatedMetric extends Model
{
    use HasFactory;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date'  => 'date',
            'type'  => CalculatedMetricType::class,
            'value' => 'float',
        ];
    }

    /**
     * Get the athlete that owns the metric.
     */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }
}
