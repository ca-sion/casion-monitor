<?php

namespace App\Models;

use App\Enums\RecoveryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RecoveryProtocol extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'                     => 'date',
            'recovery_type'            => RecoveryType::class,
            'effect_on_pain_intensity' => 'integer',
            'effectiveness_rating'     => 'integer',
        ];
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    public function relatedInjury(): BelongsTo
    {
        return $this->belongsTo(Injury::class, 'related_injury_id');
    }
}
