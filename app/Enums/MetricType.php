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

    public function getLabelShort(): ?string
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG   => 'Poids',
            self::MORNING_HRV              => 'VFC/HRV',
            self::MORNING_SLEEP_QUALITY    => 'Sommeil',
            self::MORNING_GENERAL_FATIGUE  => 'Fatigue mat.',
            self::MORNING_PAIN             => 'Douleurs',
            self::MORNING_PAIN_LOCATION    => 'Loc. douleurs',
            self::MORNING_MOOD_WELLBEING   => 'Humeur',
            self::MORNING_FIRST_DAY_PERIOD => 'Premier jour règles',

            self::PRE_SESSION_ENERGY_LEVEL  => 'Énergie',
            self::PRE_SESSION_LEG_FEEL      => 'Jambes',
            self::PRE_SESSION_SESSION_GOALS => 'Obj. séance',

            self::POST_SESSION_SESSION_LOAD       => 'Ress. charge',
            self::POST_SESSION_PERFORMANCE_FEEL   => 'Éval. perf.',
            self::POST_SESSION_SUBJECTIVE_FATIGUE => 'Eval. fatigue post',
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

    public function getScale(): ?string
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG   => null,
            self::MORNING_HRV              => null,
            self::MORNING_SLEEP_QUALITY    => 10,
            self::MORNING_GENERAL_FATIGUE  => 10,
            self::MORNING_PAIN             => 10,
            self::MORNING_PAIN_LOCATION    => 10,
            self::MORNING_MOOD_WELLBEING   => 10,
            self::MORNING_FIRST_DAY_PERIOD => null,

            self::PRE_SESSION_ENERGY_LEVEL  => 10,
            self::PRE_SESSION_LEG_FEEL      => 10,
            self::PRE_SESSION_SESSION_GOALS => null,

            self::POST_SESSION_SESSION_LOAD       => 10,
            self::POST_SESSION_PERFORMANCE_FEEL   => 10,
            self::POST_SESSION_SUBJECTIVE_FATIGUE => 10,
            self::POST_SESSION_TECHNICAL_FEEDBACK => null,
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

    public function getValueColumn(): ?string
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG   => 'value',
            self::MORNING_HRV              => 'value',
            self::MORNING_SLEEP_QUALITY    => 'value',
            self::MORNING_GENERAL_FATIGUE  => 'value',
            self::MORNING_PAIN             => 'note',
            self::MORNING_PAIN_LOCATION    => 'note',
            self::MORNING_MOOD_WELLBEING   => 'value',
            self::MORNING_FIRST_DAY_PERIOD => 'value',

            self::PRE_SESSION_ENERGY_LEVEL  => 'value',
            self::PRE_SESSION_LEG_FEEL      => 'value',
            self::PRE_SESSION_SESSION_GOALS => 'note',

            self::POST_SESSION_SESSION_LOAD       => 'value',
            self::POST_SESSION_PERFORMANCE_FEEL   => 'value',
            self::POST_SESSION_SUBJECTIVE_FATIGUE => 'value',
            self::POST_SESSION_TECHNICAL_FEEDBACK => 'note',
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
