<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\MetricType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Metric extends Model
{
    /** @use HasFactory<\Database\Factories\MetricFactory> */
    use HasFactory;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        //
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'data',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date'        => 'date',
            'metric_type' => MetricType::class,
        ];
    }

    /**
     * Get the athlete that owns the metric.
     */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    /**
     * Get the metrics's data.
     */
    protected function data(): Attribute
    {
        return Attribute::make(
            get: fn (): object => (object) [
                'date'            => $this->date->format('Y-m-d'),
                'formatted_date'  => $this->date->locale('fr_CH')->isoFormat('L'),
                'day'             => $this->date->locale('fr_CH')->isoFormat('dddd'),
                'value'           => $value = $this->{$this->metric_type?->getValueColumn()} ?? null,
                'unit'            => $unit = $this->unit ?? $this->metric_type?->getunit(),
                'scale'           => $scale = $this->metric_type?->getScale(),
                'formatted_value' => $this->formatValue($value, $unit, $scale, $this->metric_type),
                'label'           => $this->metric_type->getLabel(),
                'description'     => $this->metric_type->getDescription(),
                'value_column'    => $this->metric_type->getValueColumn(),
                'athlete'         => $this->athlete,
                'edit_link'       => route('athletes.metrics.daily.form', ['hash' => $this->athlete->hash, 'd' => $this->date->format('Y-m-d')]),
            ],
        );
    }

    /**
     * Helper to format the metric value with its unit/scale.
     * This can be moved to a helper or trait if used in many models/places.
     */
    protected function formatValue(mixed $value, ?string $unit, ?string $scale, MetricType $metricType): string
    {
        if ($value === null) {
            return 'n/a';
        }
        if ($metricType->getValueColumn() === 'note') {
            return (string) $value;
        }

        $formatted = number_format($value, $metricType->getPrecision());

        return $formatted.($unit ? ' '.$unit : ($scale ? '/'.$scale : ''));
    }
}
