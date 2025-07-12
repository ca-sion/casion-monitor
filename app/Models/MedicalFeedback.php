<?php

namespace App\Models;

use App\Enums\ProfessionalType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalFeedback extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'feedback_date' => 'date',
            'next_appointment_date' => 'date',
            'professional_type' => ProfessionalType::class,
            'reported_by_athlete' => 'boolean',
        ];
    }

    public function injury(): BelongsTo
    {
        return $this->belongsTo(Injury::class);
    }
}