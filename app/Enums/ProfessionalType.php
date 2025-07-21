<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProfessionalType: string implements HasLabel
{
    case PHYSIOTHERAPIST = 'physiotherapist';
    case DOCTOR = 'doctor';
    case OSTEOPATH = 'osteopath';
    case OTHER = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PHYSIOTHERAPIST => 'Physiothérapeute',
            self::DOCTOR          => 'Médecin',
            self::OSTEOPATH       => 'Ostéopathe',
            self::OTHER           => 'Autre',
        };
    }
}
