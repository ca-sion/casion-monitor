<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CalculatedMetric: string implements HasLabel
{
    case CIH = 'cih';
    case SBM = 'sbm';
    case CPH = 'cph';
    case RATIO_CIH_CPH = 'ratio_cih_cph';

    public function getLabel(): string
    {
        return match ($this) {
            self::CIH           => 'Charge interne hebdomadaire',
            self::SBM           => 'Score de bien-être matinal',
            self::CPH           => 'Charge planifiée hebdomadaire',
            self::RATIO_CIH_CPH => 'Ratio CIH/CPH',
        };
    }

    public function getLabelShort(): string
    {
        return match ($this) {
            self::CIH           => 'CIH',
            self::SBM           => 'SBM',
            self::CPH           => 'CPH',
            self::RATIO_CIH_CPH => 'Ratio CIH/CPH',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::CIH           => 'Somme des Charges subjectives réelles par séance (CSR-S) pour la semaine.',
            self::SBM           => 'Score agrégé des métriques de bien-être matinal.',
            self::CPH           => 'Charge d\'entraînement planifiée pour la semaine.',
            self::RATIO_CIH_CPH => 'Ratio entre la Charge interne hebdomadaire (CIH) et la Charge planifiée hebdomadaire (CPH).',
        };
    }
}
