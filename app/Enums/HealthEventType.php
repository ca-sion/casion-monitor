<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum HealthEventType: string implements HasLabel
{
    case MEDICAL_CONSULTATION = 'medical_consultation';
    case PHYSICAL_FOLLOWUP = 'physical_followup';
    case MENTAL_FOLLOWUP = 'mental_followup';
    case MASSAGE = 'massage';
    case CONTRAST_BATHS = 'contrast_baths';
    case CRYOTHERAPY = 'cryotherapy';
    case OTHER = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MEDICAL_CONSULTATION => 'Consultation médicale',
            self::PHYSICAL_FOLLOWUP    => 'Suivi physique (physio/ostéo)',
            self::MENTAL_FOLLOWUP      => 'Suivi mental/psychologique',
            self::MASSAGE              => 'Massage',
            self::CONTRAST_BATHS       => 'Bains de contraste',
            self::CRYOTHERAPY          => 'Cryothérapie',
            self::OTHER                => 'Autre',
        };
    }
}
