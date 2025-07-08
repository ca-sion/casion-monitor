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
            self::CIH           => 'Charge Interne Hebdomadaire',
            self::SBM           => 'Score de Bien-être Matinal',
            self::CPH           => 'Charge Planifiée Hebdomadaire',
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
            self::CIH           => 'Somme des Charges Subjectives Réelles par Séance (CSR-S) pour la semaine.',
            self::SBM           => 'Score agrégé des métriques de bien-être matinal.',
            self::CPH           => 'Charge d\'entraînement planifiée pour la semaine.',
            self::RATIO_CIH_CPH => 'Ratio entre la Charge Interne Hebdomadaire (CIH) et la Charge Planifiée Hebdomadaire (CPH).',
        };
    }
}
