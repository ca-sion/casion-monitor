<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Metric;
use App\Models\Athlete;
use App\Enums\MetricType;
use App\Models\CalculatedMetric;
use App\Models\TrainingPlanWeek;
use Illuminate\Support\Collection;
use App\Enums\CalculatedMetricType;

class MetricCalculationService
{
    /**
     * Orchestrates the calculation and storage of all daily calculated metrics for a given athlete and date.
     */
    public function processAndStoreDailyCalculatedMetrics(Athlete $athlete, Carbon $date): void
    {
        // 1. Fetch all necessary raw metrics for the calculations.
        $startOfWeek = $date->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $date->copy()->endOfWeek(Carbon::SUNDAY);

        // Fetch metrics for the last 28 days for all calculations (including ACWR and readiness)
        $metricsForCalculations = $athlete->metrics()
            ->where('date', '<=', $date->copy()->endOfDay())
            ->where('date', '>=', $date->copy()->subDays(34)->startOfDay())
            ->get();

        $metricsForWeek = $metricsForCalculations->whereBetween('date', [$startOfWeek, $endOfWeek]);
        $dailyMetrics = $metricsForWeek->where('date', $date);

        // 2. Calculate all the values.
        $sbm = $this->calculateSbmForCollection($dailyMetrics);
        $cih = $this->calculateCihForCollection($metricsForWeek);
        $cihNormalized = $this->calculateCihNormalizedForCollection($metricsForWeek);

        $planWeek = $athlete->currentTrainingPlanWeek;
        $cph = $planWeek ? $this->calculateCph($planWeek) : null;

        $ratioCihCph = ($cih > 0 && $cph > 0) ? $this->calculateRatio($cih, $cph) : null;
        $ratioCihNormalizedCph = ($cihNormalized > 0 && $cph > 0) ? $this->calculateRatio($cihNormalized, $cph) : null;

        // 3. Store base calculated metrics first.
        $this->storeCalculatedMetric($athlete, $date, CalculatedMetricType::SBM, $sbm);
        // $this->storeCalculatedMetric($athlete, $date, CalculatedMetricType::CIH, $cih);
        $this->storeCalculatedMetric($athlete, $date, CalculatedMetricType::CIH_NORMALIZED, $cihNormalized);
        // $this->storeCalculatedMetric($athlete, $date, CalculatedMetricType::CPH, $cph);
        // $this->storeCalculatedMetric($athlete, $date, CalculatedMetricType::RATIO_CIH_CPH, $ratioCihCph);
        $this->storeCalculatedMetric($athlete, $date, CalculatedMetricType::RATIO_CIH_NORMALIZED_CPH, $ratioCihNormalizedCph);

        // 4. Calculate and store ACWR
        $acwr = $this->calculateAcwr($metricsForCalculations, $date);
        $this->storeCalculatedMetric($athlete, $date, CalculatedMetricType::ACWR, $acwr);

        // 5. Calculate and store Readiness Score
        $readinessService = resolve(MetricReadinessService::class);
        // We use $metricsForCalculations which now contains 28 days of data, which is better for readiness.
        $readinessResult = $readinessService->calculateOverallReadinessScore($athlete, $metricsForCalculations);
        $readinessScore = $readinessResult['readiness_score'] ?? null;
        $this->storeCalculatedMetric($athlete, $date, CalculatedMetricType::READINESS_SCORE, $readinessScore);
    }

    /**
     * Helper to store a single calculated metric.
     */
    private function storeCalculatedMetric(Athlete $athlete, Carbon $date, CalculatedMetricType $type, ?float $value): void
    {
        if ($value === null || $value == 0 || ($type == CalculatedMetricType::READINESS_SCORE && $value == 100)) {
            // If value is null, we might want to delete the existing record for that day.
            CalculatedMetric::where('athlete_id', $athlete->id)
                ->where('date', $date)
                ->where('type', $type)
                ->delete();

            return;
        }

        CalculatedMetric::updateOrCreate(
            [
                'athlete_id' => $athlete->id,
                'date'       => $date,
                'type'       => $type,
            ],
            ['value' => round($value, 2)]
        );
    }

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

        return (float) $sbm;
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

        return (float) $sbm;
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

    /**
     * Calcule le ratio Aiguë:Chronique (ACWR).
     * Charge aiguë: 7 derniers jours.
     * Charge chronique: moyenne des charges aiguës des 4 dernières semaines (moyenne glissante).
     */
    public function calculateAcwr(Collection $allMetrics, Carbon $currentDate): ?float
    {
        // 1. Acute Load (last 7 days)
        $acuteStartDate = $currentDate->copy()->subDays(6)->startOfDay();
        $acuteEndDate = $currentDate->copy()->endOfDay();
        $acuteMetrics = $allMetrics->whereBetween('date', [$acuteStartDate, $acuteEndDate]);
        $acuteLoad = $this->calculateCihForCollection($acuteMetrics);

        // 2. Chronic Load (average of 4 previous weekly loads)
        $weeklyLoads = [];
        for ($i = 1; $i <= 4; $i++) {
            // Week 1 (i=1): days J-7 to J-13
            // Week 4 (i=4): days J-28 to J-34 (Requires 34 days of data in $allMetrics)

            $startDayOffset = 7 * $i; // 7, 14, 21, 28
            $endDayOffset = (7 * $i) + 6; // 13, 20, 27, 34

            // Defines the 7-day window. Dates are inclusive.
            $weekStartDate = $currentDate->copy()->subDays($endDayOffset)->startOfDay();
            $weekEndDate = $currentDate->copy()->subDays($startDayOffset)->endOfDay();

            $metricsForWeek = $allMetrics->whereBetween('date', [$weekStartDate, $weekEndDate]);

            // We only count weeks that have some activity.
            if ($metricsForWeek->isNotEmpty()) {
                $weeklyLoads[] = $this->calculateCihForCollection($metricsForWeek);
            }
        }

        if (empty($weeklyLoads)) {
            return null; // Chronic load can't be calculated.
        }

        $chronicLoad = array_sum($weeklyLoads) / count($weeklyLoads);

        // 3. ACWR Ratio
        if ($chronicLoad == 0) {
            // If chronic load is 0, ACWR is infinite or undefined.
            // If acute is also 0, it's 0/0. If acute is >0, it's a huge spike.
            // Returning a high number could be an option, but null seems safer to indicate it's a special case.
            return null;
        }

        $acwr = $acuteLoad / $chronicLoad;

        return $acwr;
    }

    /**
     * Orchestre le calcul et retourne le dernier ratio CIH/CPH pour la semaine se terminant à $endDate.
     */
    public function getLastRatioCihCph(Athlete $athlete, Carbon $endDate): float
    {
        $startOfWeek = $endDate->copy()->startOfWeek();
        $endOfWeek = $endDate->copy()->endOfWeek(Carbon::MONDAY);

        $planWeek = $athlete->currentTrainingPlan?->weeks()->where('start_date', $startOfWeek)->first();

        if (! $planWeek) {
            return 0.0; // Pas de semaine de plan correspondante
        }

        // 2. Calculer la CPH (Charge Planifiée)
        $cph = $this->calculateCph($planWeek);

        // 3. Calculer la CIH (Charge Interne)
        $metricsForWeek = $athlete->metrics()
            ->whereBetween('date', [$startOfWeek, $endOfWeek])
            ->get();

        $cih = $this->calculateCihForCollection($metricsForWeek);

        // 4. Calculer et retourner le ratio
        return $this->calculateRatio($cih, $cph);
    }
}
