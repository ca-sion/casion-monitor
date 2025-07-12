<?php

namespace App\Models;

use App\Enums\InjuryStatus;
use App\Enums\InjuryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Injury extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'declaration_date' => 'date',
            'resolved_date' => 'date',
            'session_date' => 'date',
            'injury_date' => 'date',
            'status' => InjuryStatus::class,
            'injury_type' => InjuryType::class,
            'session_related' => 'boolean',
            'immediate_onset' => 'boolean',
        ];
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    public function medicalFeedbacks(): HasMany
    {
        return $this->hasMany(MedicalFeedback::class);
    }

    public function recoveryProtocols(): HasMany
    {
        return $this->hasMany(RecoveryProtocol::class, 'related_injury_id');
    }
}