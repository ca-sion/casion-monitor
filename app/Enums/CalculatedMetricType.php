<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CalculatedMetricType: string implements HasLabel
{
    case CIH = 'cih';
    case CIH_NORMALIZED = 'cih_normalized';
    case SBM = 'sbm';
    case CPH = 'cph';
    case RATIO_CIH_CPH = 'ratio_cih_cph';
    case RATIO_CIH_NORMALIZED_CPH = 'ratio_cih_normalized_cph';
    case READINESS_SCORE = 'readiness_score';

    public function getLabel(): string
    {
        return match ($this) {
            self::CIH                      => 'Charge interne hebdomadaire',
            self::CIH_NORMALIZED           => 'Charge interne hebdomadaire normalisée',
            self::SBM                      => 'Score de bien-être matinal',
            self::CPH                      => 'Charge planifiée hebdomadaire',
            self::RATIO_CIH_CPH            => 'Ratio CIH/CPH',
            self::RATIO_CIH_NORMALIZED_CPH => 'Ratio CIH normalisée/CPH',
            self::READINESS_SCORE          => 'Readiness',
        };
    }

    public function getLabelShort(): string
    {
        return match ($this) {
            self::CIH                      => 'CIH',
            self::CIH_NORMALIZED           => 'CIH-N',
            self::SBM                      => 'SBM',
            self::CPH                      => 'CPH',
            self::RATIO_CIH_CPH            => 'CIH/CPH',
            self::RATIO_CIH_NORMALIZED_CPH => 'CIH-N/CPH',
            self::READINESS_SCORE          => 'Read.',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::CIH                      => 'Somme des Charges subjectives réelles par séance (CSR-S) pour la semaine.',
            self::CIH_NORMALIZED           => 'Somme des Charges subjectives réelles par séance (CSR-S) normalisée pour la comparabilité avec la CPH.',
            self::SBM                      => 'Score agrégé des métriques de bien-être matinal, basé sur l\'Indice de Hooper.',
            self::CPH                      => 'Charge d\'entraînement planifiée pour la semaine.',
            self::RATIO_CIH_CPH            => 'Ratio entre la Charge interne hebdomadaire (CIH) et la Charge planifiée hebdomadaire (CPH).',
            self::RATIO_CIH_NORMALIZED_CPH => 'Ratio entre la Charge interne hebdomadaire normalisée (CIH-N) et la Charge planifiée hebdomadaire (CPH).',
            self::READINESS_SCORE          => 'Indique à quel point le corps est prêt pour l\'entraînement et la performance chaque jour.',
        };
    }

    public function getScale(): ?string
    {
        return match ($this) {
            self::CIH                      => 70,
            self::CIH_NORMALIZED           => 10,
            self::SBM                      => 10,
            self::CPH                      => 10,
            self::RATIO_CIH_CPH            => 1,
            self::RATIO_CIH_NORMALIZED_CPH => 1,
            self::READINESS_SCORE          => 100,
        };
    }

    /**
     * Retourne la direction optimale de la tendance pour cette métrique calculée.
     * 'good': une augmentation est généralement positive.
     * 'bad': une augmentation est généralement négative.
     * 'neutral': la direction n'a pas de signification intrinsèque positive/négative (ex: poids).
     */
    public function getTrendOptimalDirection(): string
    {
        return match ($this) {
            self::CIH                      => 'bad',
            self::CIH_NORMALIZED           => 'bad',
            self::SBM                      => 'good',
            self::CPH                      => 'neutral',
            self::RATIO_CIH_CPH            => 'neutral',
            self::RATIO_CIH_NORMALIZED_CPH => 'neutral',
            self::READINESS_SCORE          => 'neutral',
        };
    }

    /**
     * Retourne l'icône iconify tailwind.
     */
    public function getIconifyTailwind(): ?string
    {
        return match ($this) {
            self::CIH                      => 'icon-[material-symbols-light--clock-arrow-down-outline]',
            self::CIH_NORMALIZED           => 'icon-material-symbols-light--clock-arrow-down-outline]',
            self::SBM                      => 'icon-[material-symbols-light--digital-wellbeing-outline]',
            self::CPH                      => 'icon-[material-symbols-light--calendar-clock-outline]',
            self::RATIO_CIH_CPH            => 'icon-[material-symbols-light--align-center]',
            self::RATIO_CIH_NORMALIZED_CPH => 'icon-[material-symbols-light--align-center]',
            self::READINESS_SCORE          => 'icon-[material-symbols-light--readiness-score-outline]',
        };
    }

    /**
     * Retourne la couleur.
     */
    public function getColor(): ?string
    {
        return match ($this) {
            self::CIH                      => 'zinc',
            self::CIH_NORMALIZED           => 'zinc',
            self::SBM                      => 'zinc',
            self::CPH                      => 'zinc',
            self::RATIO_CIH_CPH            => 'zinc',
            self::RATIO_CIH_NORMALIZED_CPH => 'zinc',
            self::READINESS_SCORE          => 'zinc',
        };
    }
}
