<?php

namespace App\Models;

use App\Enums\ProfessionalType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MedicalFeedback extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string|null
     */
    protected $table = 'medical_feedbacks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'feedback_date'         => 'date',
            'next_appointment_date' => 'date',
            'professional_type'     => ProfessionalType::class,
            'reported_by_athlete'   => 'boolean',
        ];
    }

    public function injury(): BelongsTo
    {
        return $this->belongsTo(Injury::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }
}
