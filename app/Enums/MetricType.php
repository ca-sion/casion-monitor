<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MetricType: string implements HasLabel
{
    // Métriques "Au réveil"
    case MORNING_BODY_WEIGHT_KG = 'morning_body_weight_kg';
    case MORNING_HRV = 'morning_hrv';
    case MORNING_SLEEP_QUALITY = 'morning_sleep_quality';
    case MORNING_GENERAL_FATIGUE = 'morning_general_fatigue';
    case MORNING_PAIN = 'morning_pain';
    case MORNING_PAIN_LOCATION = 'morning_pain_location';
    case MORNING_MOOD_WELLBEING = 'morning_mood_wellbeing';
    case MORNING_FIRST_DAY_PERIOD = 'morning_first_day_period';

    // Métriques "Avant la session"
    case PRE_SESSION_ENERGY_LEVEL = 'pre_session_energy_level';
    case PRE_SESSION_LEG_FEEL = 'pre_session_leg_feel';
    case PRE_SESSION_SESSION_GOALS = 'pre_session_session_goals'; // La valeur sera dans 'notes'

    // Métriques "Après la session"
    case POST_SESSION_SESSION_LOAD = 'post_session_session_load';
    case POST_SESSION_PERFORMANCE_FEEL = 'post_session_performance_feel';
    case POST_SESSION_SUBJECTIVE_FATIGUE = 'post_session_subjective_fatigue';
    case POST_SESSION_TECHNICAL_FEEDBACK = 'post_session_technical_feedback'; // La valeur sera dans 'notes'

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG   => 'Poids corporel le matin',
            self::MORNING_HRV              => 'Variabilité de la fréquence cardiaque (VFC/HRV)',
            self::MORNING_SLEEP_QUALITY    => 'Qualité du sommeil',
            self::MORNING_GENERAL_FATIGUE  => 'Fatigue générale',
            self::MORNING_PAIN             => 'Douleurs musculaires/articulaires',
            self::MORNING_PAIN_LOCATION    => 'Localisation des douleurs',
            self::MORNING_MOOD_WELLBEING   => 'Humeur/bien-être',
            self::MORNING_FIRST_DAY_PERIOD => 'Premier jour des règles',

            self::PRE_SESSION_ENERGY_LEVEL  => "Niveau d'énergie",
            self::PRE_SESSION_LEG_FEEL      => 'Ressenti des jambes',
            self::PRE_SESSION_SESSION_GOALS => 'Objectifs de la séance',

            self::POST_SESSION_SESSION_LOAD       => 'Ressenti de la charge',
            self::POST_SESSION_PERFORMANCE_FEEL   => 'Évaluation de la performance',
            self::POST_SESSION_SUBJECTIVE_FATIGUE => 'Evaluation de la fatigue',
            self::POST_SESSION_TECHNICAL_FEEDBACK => 'Feedback',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG   => 'Poids corporel le matin.',
            self::MORNING_HRV              => 'Valeur de la Variabilité de la fréquence cardiaque (HRV) au matin.',
            self::MORNING_SLEEP_QUALITY    => 'Évaluation subjective de la qualité du sommeil au matin.',
            self::MORNING_GENERAL_FATIGUE  => 'Évaluation subjective de la fatigue générale au matin.',
            self::MORNING_PAIN             => 'Évaluation subjective des douleurs musculaires/articulaires au matin.',
            self::MORNING_PAIN_LOCATION    => "Localisation des douleurs si l'évaluation subjective des douleurs au matin est supérieure à 3.",
            self::MORNING_MOOD_WELLBEING   => "Évaluation subjective de l'humeur/bien-être au matin.",
            self::MORNING_FIRST_DAY_PERIOD => "Indique si c'est le premier jour des règles (pour les femmes).",

            self::PRE_SESSION_ENERGY_LEVEL  => "Évaluation subjective du niveau d'énergie perçu avant la session.",
            self::PRE_SESSION_LEG_FEEL      => 'Évaluation subjective du ressenti des jambes avant la session.',
            self::PRE_SESSION_SESSION_GOALS => 'Objectifs de la séance.',

            self::POST_SESSION_SESSION_LOAD       => 'Évaluation subjective de la charge après la session.',
            self::POST_SESSION_PERFORMANCE_FEEL   => 'Évaluation subjective du ressenti de la performance après la session.',
            self::POST_SESSION_SUBJECTIVE_FATIGUE => 'Évaluation subjective de la fatigue après la session.',
            self::POST_SESSION_TECHNICAL_FEEDBACK => 'Feedback technique et sensations de la séance après la session.',
        };
    }

    public function getScaleHint(): ?string
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG   => null,
            self::MORNING_HRV              => null,
            self::MORNING_SLEEP_QUALITY    => 'très mauvaise ➝ excellente',
            self::MORNING_GENERAL_FATIGUE  => 'pas fatigué ➝ épuisé',
            self::MORNING_PAIN             => 'aucune ➝ très fortes',
            self::MORNING_PAIN_LOCATION    => null,
            self::MORNING_MOOD_WELLBEING   => 'très mauvaise ➝ excellente',
            self::MORNING_FIRST_DAY_PERIOD => null,

            self::PRE_SESSION_ENERGY_LEVEL  => 'très bas ➝ très haut',
            self::PRE_SESSION_LEG_FEEL      => 'très lourdes ➝ très légères',
            self::PRE_SESSION_SESSION_GOALS => null,

            self::POST_SESSION_SESSION_LOAD       => 'basse ➝ très haute',
            self::POST_SESSION_PERFORMANCE_FEEL   => 'mauvais ➝ excellent',
            self::POST_SESSION_SUBJECTIVE_FATIGUE => 'aucune ➝ extrême',
            self::POST_SESSION_TECHNICAL_FEEDBACK => null,
        };
    }

    public function getHint(): ?string
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG   => 'En kilogrammes',
            self::MORNING_HRV              => 'En millisecondes',
            self::MORNING_SLEEP_QUALITY    => '1: très mauvaise, 10: excellente',
            self::MORNING_GENERAL_FATIGUE  => '1: pas fatigué, 10: épuisé',
            self::MORNING_PAIN             => '1: aucune, 10: très fortes',
            self::MORNING_PAIN_LOCATION    => null,
            self::MORNING_MOOD_WELLBEING   => '1: très mauvaise, 10: excellente',
            self::MORNING_FIRST_DAY_PERIOD => null,

            self::PRE_SESSION_ENERGY_LEVEL  => '1: très bas, 10: très haut',
            self::PRE_SESSION_LEG_FEEL      => '1: très lourdes, 10: très légères',
            self::PRE_SESSION_SESSION_GOALS => null,

            self::POST_SESSION_SESSION_LOAD       => '1: basse, 10: très haute',
            self::POST_SESSION_PERFORMANCE_FEEL   => '1: mauvais, 10: excellent',
            self::POST_SESSION_SUBJECTIVE_FATIGUE => '1: aucune, 10: extrême',
            self::POST_SESSION_TECHNICAL_FEEDBACK => null,
        };
    }

    public function getUnit(): ?string
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG   => 'kg',
            self::MORNING_HRV              => 'ms',
            self::MORNING_SLEEP_QUALITY    => null,
            self::MORNING_GENERAL_FATIGUE  => null,
            self::MORNING_PAIN             => null,
            self::MORNING_PAIN_LOCATION    => null,
            self::MORNING_MOOD_WELLBEING   => null,
            self::MORNING_FIRST_DAY_PERIOD => null,

            self::PRE_SESSION_ENERGY_LEVEL  => null,
            self::PRE_SESSION_LEG_FEEL      => null,
            self::PRE_SESSION_SESSION_GOALS => null,

            self::POST_SESSION_SESSION_LOAD       => null,
            self::POST_SESSION_PERFORMANCE_FEEL   => null,
            self::POST_SESSION_SUBJECTIVE_FATIGUE => null,
            self::POST_SESSION_TECHNICAL_FEEDBACK => null,
        };
    }
}
