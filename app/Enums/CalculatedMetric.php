<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CalculatedMetric: string implements HasLabel
{
    case CIH = 'cih';
    case CIH_NORMALIZED = 'cih_normalized';
    case SBM = 'sbm';
    case CPH = 'cph';
    case RATIO_CIH_CPH = 'ratio_cih_cph';
    case RATIO_CIH_NORMALIZED_CPH = 'ratio_cih_normalized_cph';

    public function getLabel(): string
    {
        return match ($this) {
            self::CIH           => 'Charge interne hebdomadaire',
            self::CIH_NORMALIZED  => 'Charge interne hebdomadaire normalisée',
            self::SBM           => 'Score de bien-être matinal',
            self::CPH           => 'Charge planifiée hebdomadaire',
            self::RATIO_CIH_CPH => 'Ratio CIH/CPH',
            self::RATIO_CIH_NORMALIZED_CPH => 'Ratio CIH normalisée/CPH',
        };
    }

    public function getLabelShort(): string
    {
        return match ($this) {
            self::CIH           => 'CIH',
            self::CIH_NORMALIZED  => 'CIH-N',
            self::SBM           => 'SBM',
            self::CPH           => 'CPH',
            self::RATIO_CIH_CPH => 'Ratio CIH/CPH',
            self::RATIO_CIH_NORMALIZED_CPH => 'Ratio CIH Normalisée/CPH',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::CIH           => 'Somme des Charges subjectives réelles par séance (CSR-S) pour la semaine.',
            self::CIH_NORMALIZED  => 'Charge interne hebdomadaire normalisée pour la comparabilité avec la CPH.',
            self::SBM           => 'Score agrégé des métriques de bien-être matinal.',
            self::CPH           => 'Charge d\'entraînement planifiée pour la semaine.',
            self::RATIO_CIH_CPH => 'Ratio entre la Charge interne hebdomadaire (CIH) et la Charge planifiée hebdomadaire (CPH).',
            self::RATIO_CIH_NORMALIZED_CPH => 'Ratio entre la Charge interne hebdomadaire normalisée (CIH Normalisée) et la Charge planifiée hebdomadaire (CPH).',
        };
    }
}
