<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InjuryStatus: string implements HasLabel
{
    case DECLARED = 'declared';
    case UNDER_DIAGNOSIS = 'diagnosis';
    case IN_TREATMENT = 'treatment';
    case IN_REHABILITATION = 'rehab';
    case PROGRESSIVE_RETURN = 'return';
    case RESOLVED = 'resolved';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DECLARED           => 'Déclarée',
            self::UNDER_DIAGNOSIS    => 'En diagnostic',
            self::IN_TREATMENT       => 'En traitement',
            self::IN_REHABILITATION  => 'En rééducation',
            self::PROGRESSIVE_RETURN => 'Retour progressif',
            self::RESOLVED           => 'Guérie',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::DECLARED           => 'rose',
            self::UNDER_DIAGNOSIS    => 'pink',
            self::IN_TREATMENT       => 'fuchsia',
            self::IN_REHABILITATION  => 'teal',
            self::PROGRESSIVE_RETURN => 'emerals',
            self::RESOLVED           => 'lime',
        };
    }
}
