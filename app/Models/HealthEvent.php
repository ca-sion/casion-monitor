<?php

namespace App\Models;

use App\Enums\HealthEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HealthEvent extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type'                     => HealthEventType::class,
            'date'                     => 'date',
            'effect_on_pain_intensity' => 'integer',
            'effectiveness_rating'     => 'integer',
            'reported_by_athlete'      => 'boolean',
            'is_private'               => 'boolean',
        ];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function injury(): BelongsTo
    {
        return $this->belongsTo(Injury::class);
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }
}
