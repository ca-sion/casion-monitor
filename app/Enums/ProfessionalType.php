<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProfessionalType: string implements HasLabel
{
    case PHYSIOTHERAPIST = 'physiotherapist';
    case DOCTOR = 'doctor';
    case OSTEOPATH = 'osteopath';
    case PSYCHOLOGIST = 'psychologist';
    case DIETICIAN = 'dietician';
    case MASSEUR = 'masseur';
    case MENTAL_COACH = 'mental_coach';
    case OTHER = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PHYSIOTHERAPIST => 'Physiothérapeute',
            self::DOCTOR          => 'Médecin',
            self::OSTEOPATH       => 'Ostéopathe',
            self::PSYCHOLOGIST    => 'Psyhologue',
            self::DIETICIAN       => 'Diététicien',
            self::MASSEUR         => 'Masseur',
            self::MENTAL_COACH    => 'Coach mental',
            self::OTHER           => 'Autre',
        };
    }
}
