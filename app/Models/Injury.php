<?php

namespace App\Models;

use App\Enums\BodyPart;
use App\Enums\InjuryType;
use App\Enums\InjuryStatus;
use App\Observers\InjuryObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy([InjuryObserver::class])]
class Injury extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'declaration_date' => 'date',
            'resolved_date'    => 'date',
            'session_date'     => 'date',
            'injury_date'      => 'date',
            'status'           => InjuryStatus::class,
            'injury_type'      => InjuryType::class,
            'pain_location'    => BodyPart::class,
            'session_related'  => 'boolean',
            'immediate_onset'  => 'boolean',
        ];
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    public function healthEvents(): HasMany
    {
        return $this->hasMany(HealthEvent::class);
    }
}
