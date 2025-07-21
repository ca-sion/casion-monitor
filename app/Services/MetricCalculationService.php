<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Metric;
use App\Models\Athlete;
use App\Enums\MetricType;
use App\Models\TrainingPlanWeek;
use Illuminate\Support\Collection;

class MetricCalculationService
{
    /**
     * Calcule le Score de Bien-être Matinal (SBM) pour un seul jour à partir d'une collection de métriques.
     */
    public function calculateSbmForCollection(Collection $dailyMetrics): ?float
    {
        $sbmSum = 0;
        $maxPossibleSbm = 0;

        $sleepQuality = $dailyMetrics->firstWhere('metric_type', MetricType::MORNING_SLEEP_QUALITY)?->value;
        if ($sleepQuality !== null) {
            $sbmSum += $sleepQuality;
            $maxPossibleSbm += 10;
        }

        $generalFatigue = $dailyMetrics->firstWhere('metric_type', MetricType::MORNING_GENERAL_FATIGUE)?->value;
        if ($generalFatigue !== null) {
            $sbmSum += (10 - $generalFatigue);
            $maxPossibleSbm += 10;
        }

        $pain = $dailyMetrics->firstWhere('metric_type', MetricType::MORNING_PAIN)?->value;
        if ($pain !== null) {
            $sbmSum += (10 - $pain);
            $maxPossibleSbm += 10;
        }

        $moodWellbeing = $dailyMetrics->firstWhere('metric_type', MetricType::MORNING_MOOD_WELLBEING)?->value;
        if ($moodWellbeing !== null) {
            $sbmSum += $moodWellbeing;
            $maxPossibleSbm += 10;
        }

        if ($maxPossibleSbm === 0) {
            return null;
        }

        $smb = (($sbmSum / $maxPossibleSbm) * 10);

        $sbm = number_format($smb, 1);

        return (float) $smb;
    }

    /**
     * Calcule la Charge Subjective Réelle par Séance (CSR-S) pour une métrique de RPE donnée.
     */
    public function calculateCsrS(Metric $rpeMetric): float
    {
        if ($rpeMetric->metric_type !== MetricType::POST_SESSION_SESSION_LOAD || ! is_numeric($rpeMetric->value)) {
            return 0.0;
        }

        return (float) $rpeMetric->value;
    }

    /**
     * Calcule la Charge Interne Hebdomadaire (CIH) pour une semaine donnée et un athlète.
     * CIH est la somme des Charges Subjectives Réelles par Séance (CSR-S) pour chaque séance de la semaine.
     */
    public function calculateCihForCollection(Collection $metricsForWeek): float
    {
        $cih = $metricsForWeek->where('metric_type', MetricType::POST_SESSION_SESSION_LOAD->value)->sum('value');

        return (float) $cih;
    }

    /**
     * Calcule la Charge Interne Hebdomadaire Normalisée (CIH_NORMALIZED) pour une semaine donnée à partir d'une collection de métriques.
     * CIH_NORMALIZED est calculée comme : Somme des POST_SESSION_SESSION_LOAD / Nombre de jours avec POST_SESSION_SESSION_LOAD.
     */
    public function calculateCihNormalizedForCollection(Collection $metricsForWeek): float
    {
        $rpeMetrics = $metricsForWeek->where('metric_type', MetricType::POST_SESSION_SESSION_LOAD->value);

        if ($rpeMetrics->isEmpty()) {
            return 0.0;
        }

        $sumRpe = $rpeMetrics->sum('value');
        $distinctDays = $rpeMetrics->pluck('date')->unique()->count();

        if ($distinctDays === 0) {
            return 0.0;
        }

        $averageSessionLoad = $sumRpe / $distinctDays;

        $averageSessionLoad = number_format($averageSessionLoad, 1);

        return (float) ($averageSessionLoad);
    }

    /**
     * Calcule le Score de Bien-être Matinal (SBM) pour un jour donné et un athlète.
     * SBM est calculé comme suit : MORNING_SLEEP_QUALITY + (10 - MORNING_GENERAL_FATIGUE) + (10 - MORNING_PAIN) + MORNING_MOOD_WELLBEING.
     */
    public function calculateSbm(Athlete $athlete, Carbon $date): ?float
    {
        $sbmSum = 0;
        $maxPossibleSbm = 0;

        $sleepQuality = $athlete->metrics()->where('date', $date->toDateString())->where('metric_type', MetricType::MORNING_SLEEP_QUALITY->value)->first()?->value;
        if ($sleepQuality !== null) {
            $sbmSum += $sleepQuality;
            $maxPossibleSbm += 10;
        }

        $generalFatigue = $athlete->metrics()->where('date', $date->toDateString())->where('metric_type', MetricType::MORNING_GENERAL_FATIGUE->value)->first()?->value;
        if ($generalFatigue !== null) {
            $sbmSum += (10 - $generalFatigue);
            $maxPossibleSbm += 10;
        }

        $pain = $athlete->metrics()->where('date', $date->toDateString())->where('metric_type', MetricType::MORNING_PAIN->value)->first()?->value;
        if ($pain !== null) {
            $sbmSum += (10 - $pain);
            $maxPossibleSbm += 10;
        }

        $moodWellbeing = $athlete->metrics()->where('date', $date->toDateString())->where('metric_type', MetricType::MORNING_MOOD_WELLBEING->value)->first()?->value;
        if ($moodWellbeing !== null) {
            $sbmSum += $moodWellbeing;
            $maxPossibleSbm += 10;
        }

        if ($maxPossibleSbm === 0) {
            return null;
        }

        $smb = (($sbmSum / $maxPossibleSbm) * 10);

        $sbm = number_format($smb, 1);

        return (float) $smb;
    }

    /**
     * Calcule la Charge Planifiée Hebdomadaire (CPH) pour une semaine donnée.
     * CPH est calculée comme : CPH=(V+1)+(MAX(0,sqrt(I−50​))×0.25).
     */
    public function calculateCph(TrainingPlanWeek $planWeek): float
    {
        $volumePlanned = $planWeek->volume_planned ?? 0;
        $intensityPlanned = $planWeek->intensity_planned ?? 0;
        $intensityWeight = 0.25;

        if ($volumePlanned == 0 && $intensityPlanned == 0) {
            return (float) 0.0;
        }

        $cph = ($volumePlanned + 1) + max([0, (sqrt($intensityPlanned - 50) * $intensityWeight)]);

        $cph = number_format($cph, 1);

        return (float) $cph;
    }

    /**
     * Calcule le Ratio CIH / CPH.
     */
    public function calculateRatio(float $cih, float $cph): float
    {
        if ($cph === 0.0) {
            return 0.0;
        }

        return $cih / $cph;
    }
}
