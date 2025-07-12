<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InjuryType: string implements HasLabel
{
    case MUSCULAR = 'muscular';
    case JOINT = 'joint';
    case TENDINITIS = 'tendinitis';
    case FRACTURE = 'fracture';
    case SPRAIN = 'sprain';
    case PERSISTENT_PAIN = 'persistent_pain';
    case OTHER = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MUSCULAR        => 'Musculaire',
            self::JOINT           => 'Articulaire',
            self::TENDINITIS      => 'Tendinite',
            self::FRACTURE        => 'Fracture',
            self::SPRAIN          => 'Entorse',
            self::PERSISTENT_PAIN => 'Douleur persistante non diagnostiquée',
            self::OTHER           => 'Autre',
        };
    }
}