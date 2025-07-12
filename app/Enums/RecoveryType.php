<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RecoveryType: string implements HasLabel
{
    case MASSAGE = 'massage';
    case TARGETED_STRETCHES = 'targeted_stretches';
    case CONTRAST_BATHS = 'contrast_baths';
    case ACTIVE_REST = 'active_rest';
    case CRYOTHERAPY = 'cryotherapy';
    case ADDITIONAL_SLEEP = 'additional_sleep';
    case OTHER = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MASSAGE            => 'Massage',
            self::TARGETED_STRETCHES => 'Étirements ciblés',
            self::CONTRAST_BATHS     => 'Bains de contraste',
            self::ACTIVE_REST        => 'Repos actif',
            self::CRYOTHERAPY        => 'Cryothérapie',
            self::ADDITIONAL_SLEEP   => 'Sommeil additionnel',
            self::OTHER              => 'Autre',
        };
    }
}