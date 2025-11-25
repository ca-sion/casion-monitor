<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Athlete;
use App\Enums\MetricType;
use App\Models\CalculatedMetric;
use Illuminate\Support\Collection;
use App\Enums\CalculatedMetricType;

class MetricAlertsService
{
    protected MetricCalculationService $metricCalculationService;

    protected MetricTrendsService $metricTrendsService;

    protected MetricReadinessService $metricReadinessService;

    protected MetricMenstrualService $metricMenstrualService;

    public function __construct(MetricCalculationService $metricCalculationService, MetricTrendsService $metricTrendsService, MetricReadinessService $metricReadinessService, MetricMenstrualService $metricMenstrualService)
    {
        $this->metricCalculationService = $metricCalculationService;
        $this->metricTrendsService = $metricTrendsService;
        $this->metricReadinessService = $metricReadinessService;
        $this->metricMenstrualService = $metricMenstrualService;
    }

    private const ALERT_THRESHOLDS = [
        MetricType::MORNING_BODY_WEIGHT_KG->value => [
            'z_score_high' => 2.0,
            'z_score_low'  => -2.0,
        ],
        MetricType::MORNING_HRV->value => [
            'z_score_high' => 2.0,
            'z_score_low'  => -1.8,
        ],
        MetricType::MORNING_SLEEP_QUALITY->value => [
            'persistent_low_7d_max'  => 4,
            'persistent_low_30d_max' => 5,
            'z_score_high'           => 2.0,
            'z_score_low'            => -1.8,
        ],
        MetricType::MORNING_GENERAL_FATIGUE->value => [
            'persistent_high_7d_min'  => 7,
            'persistent_high_30d_min' => 6,
            'elevated_7d_min'         => 5,
            'elevated_30d_min'        => 5,
            'z_score_high'            => 1.8,
            'z_score_low'             => -2.0,
        ],
        MetricType::MORNING_PAIN->value => [
            'persistent_high_7d_min' => 5,
            'declared_high_min'      => 4,
            'z_score_high'           => 1.8,
            'z_score_low'            => -2.0,
        ],
        MetricType::MORNING_MOOD_WELLBEING->value => [
            'z_score_high' => 2.0,
            'z_score_low'  => -1.8,
        ],
        MetricType::PRE_SESSION_ENERGY_LEVEL->value => [
            'z_score_high' => 2.0,
            'z_score_low'  => -1.8,
        ],
        MetricType::PRE_SESSION_LEG_FEEL->value => [
            'z_score_high' => 2.0,
            'z_score_low'  => -1.8,
        ],
        MetricType::POST_SESSION_SESSION_LOAD->value => [
            'z_score_high' => 1.8,
            'z_score_low'  => -2.0,
        ],
        MetricType::POST_SESSION_PERFORMANCE_FEEL->value => [
            'z_score_high' => 2.0,
            'z_score_low'  => -1.8,
        ],
        MetricType::POST_SESSION_SUBJECTIVE_FATIGUE->value => [
            'z_score_high' => 1.8,
            'z_score_low'  => -2.0,
        ],
        MetricType::POST_SESSION_PAIN->value => [
            'declared_high_min' => 4,
            'z_score_high'      => 1.8,
            'z_score_low'       => -2.0,
        ],
        CalculatedMetricType::RATIO_CIH_NORMALIZED_CPH->value => [
            'ratio_underload_threshold' => 0.8,
            'ratio_overload_threshold'  => 1.3,
        ],
        CalculatedMetricType::SBM->value => [
            'average_low_threshold'  => 4.0,
            'average_high_threshold' => 7.5,
        ],
        'MENSTRUAL_CYCLE' => [
            'amenorrhea_days_beyond_avg'    => 60,
            'oligomenorrhea_min_cycle'      => 21,
            'oligomenorrhea_max_cycle'      => 35,
            'delayed_cycle_days_beyond_avg' => 2,
            'prolonged_absence_no_avg'      => 45,
            'menstrual_fatigue_min'         => 7,
            'menstrual_perf_feel_max'       => 4,
        ],
    ];

    public const PAIN_METRICS = [
        MetricType::MORNING_PAIN,
        MetricType::MORNING_PAIN_LOCATION,
        MetricType::POST_SESSION_PAIN,
    ];

    public function getAlerts(Athlete $athlete, Collection $athleteMetrics, Collection $athletePlanWeeks, array $options): array
    {
        $allAlerts = [];
        $includeAlerts = $options['include_alerts'] ?? [];

        $calculatedMetrics = CalculatedMetric::where('athlete_id', $athlete->id)
            ->where('date', '>=', now()->subDays(90))
            ->get();

        if (in_array('general', $includeAlerts)) {
            $allAlerts = array_merge($allAlerts, $this->getGeneralAlerts($athlete, $athleteMetrics));
        }

        if (in_array('charge', $includeAlerts)) {
            $allAlerts = array_merge($allAlerts, $this->getChargeAlerts($athlete, $athleteMetrics, $athletePlanWeeks, $calculatedMetrics));
        }

        if (in_array('readiness', $includeAlerts)) {
            $allAlerts = array_merge($allAlerts, $this->getReadinessAlerts($athlete, $athleteMetrics));
        }

        if (in_array('menstrual', $includeAlerts) && $athlete->gender?->value === 'w') {
            $allAlerts = array_merge($allAlerts, $this->getMenstrualAlerts($athlete, $athleteMetrics));
        }

        return $allAlerts;
    }

    protected function getGeneralAlerts(Athlete $athlete, Collection $metrics): array
    {
        $alerts = [];

        $generalWellbeingMetricsForZScore = [
            MetricType::MORNING_HRV,
            MetricType::MORNING_GENERAL_FATIGUE,
            MetricType::MORNING_SLEEP_QUALITY,
            MetricType::MORNING_BODY_WEIGHT_KG,
            MetricType::MORNING_PAIN,
            MetricType::MORNING_MOOD_WELLBEING,
            MetricType::PRE_SESSION_ENERGY_LEVEL,
            MetricType::PRE_SESSION_LEG_FEEL,
            MetricType::POST_SESSION_PERFORMANCE_FEEL,
            MetricType::POST_SESSION_SESSION_LOAD,
            MetricType::POST_SESSION_SUBJECTIVE_FATIGUE,
        ];

        foreach ($generalWellbeingMetricsForZScore as $metricType) {
            $this->checkZScoreAlerts($athlete, $metrics, $metricType, $alerts);
        }

        $this->checkSleepQualityAlerts($athlete, $metrics, $alerts);
        $this->checkFatigueAlerts($athlete, $metrics, $alerts);
        $this->checkPainAlerts($athlete, $metrics, $alerts);

        // Gérer les cas où aucune alerte spécifique n'a été détectée.
        // On vérifie d'abord s'il y a suffisamment de données pour une analyse.
        if ($metrics->isEmpty() || $metrics->count() < 5) {
            $this->addAlert($alerts, 'info', 'Pas encore suffisamment de données enregistrées pour une analyse complète.');
        }

        return $alerts;
    }

    protected function getChargeAlerts(Athlete $athlete, Collection $athleteMetrics, Collection $athletePlanWeeks, Collection $calculatedMetrics): array
    {
        $alerts = [];
        $weekStartDate = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $sevenDaysBefore = Carbon::now()->subDays(7)->startOfDay();

        $alerts = array_merge($alerts, $this->evaluateCihCphRatioAlerts($athlete, $weekStartDate, $calculatedMetrics));
        $alerts = array_merge($alerts, $this->evaluateSbmAlerts($athlete, $sevenDaysBefore, $calculatedMetrics));

        return $alerts;
    }

    protected function getReadinessAlerts(Athlete $athlete, Collection $athleteMetrics): array
    {
        $alerts = [];
        $readinessStatus = $this->metricReadinessService->getAthleteReadinessStatus($athlete, $athleteMetrics);
        if ($readinessStatus['level'] !== 'green') {
            $this->addAlert($alerts, $readinessStatus['level'], $readinessStatus['message']);
        }

        return $alerts;
    }

    protected function getMenstrualAlerts(Athlete $athlete, Collection $athleteMetrics): array
    {
        $alerts = [];
        $menstrualThresholds = self::ALERT_THRESHOLDS['MENSTRUAL_CYCLE'];
        $cycleData = $this->metricMenstrualService->deduceMenstrualCyclePhase($athlete, $athleteMetrics);

        // 1. Aménorrhée ou Oligoménorrhée (détecté par la phase calculée)
        if ($cycleData['phase'] === 'Aménorrhée' || $cycleData['phase'] === 'Oligoménorrhée') {
            $this->addAlert($alerts, 'danger', 'Cycle menstruel irrégulier (moy. '.$cycleData['cycle_length_avg'].' jours). Il est suggéré de consulter pour évaluer un potentiel RED-S.');
        }
        // 2. Absence de règles prolongée sans données de cycle moyen (cas où 'deduceMenstrualCyclePhase' n'aurait pas pu déterminer la phase 'Aménorrhée' faute de données de moyenne)
        elseif ($cycleData['cycle_length_avg'] === null && $cycleData['last_period_start']) {
            $daysSinceLastPeriod = Carbon::parse($cycleData['last_period_start'])->diffInDays(Carbon::now());
            if ($daysSinceLastPeriod > $menstrualThresholds['prolonged_absence_no_avg']) {
                $this->addAlert($alerts, 'danger', 'Absence de règles prolongée ('.$daysSinceLastPeriod.' jours depuis les dernières règles). Forte suspicion de RED-S. Consultation médicale impérative.');
            }
        }
        // 3. Potentiel retard ou cycle long avec une moyenne de cycle NORMAL (21-35 jours)
        elseif ($cycleData['phase'] === 'Potentiel retard ou cycle long'
            && $cycleData['cycle_length_avg'] >= $menstrualThresholds['oligomenorrhea_min_cycle']
            && $cycleData['cycle_length_avg'] <= $menstrualThresholds['oligomenorrhea_max_cycle']) {
            $this->addAlert($alerts, 'warning', 'Retard du cycle menstruel (moy. '.$cycleData['cycle_length_avg'].' jours). Suggéré de surveiller.');
        }
        // 4. Phase 'Inconnue' en raison de l'absence de données J1 (priorité faible)
        elseif ($cycleData['phase'] === 'Inconnue' && $cycleData['reason'] === 'Enregistrez au moins deux J1 pour calculer la durée moyenne de votre cycle.') {
            $this->addAlert($alerts, 'info', 'Aucune donnée récente sur le premier jour des règles pour cette athlète. Un suivi est recommandé.');
        }

        // 5. Corrélation entre phase menstruelle et performance/fatigue
        if ($cycleData['phase'] === 'Menstruelle') {
            $currentDayFatigue = $athleteMetrics->firstWhere('metric_type', MetricType::MORNING_GENERAL_FATIGUE)?->value;
            $currentDayPerformanceFeel = $athleteMetrics->firstWhere('metric_type', MetricType::POST_SESSION_PERFORMANCE_FEEL)?->value;

            if ($currentDayFatigue !== null && $currentDayFatigue >= $menstrualThresholds['menstrual_fatigue_min']) {
                $this->addAlert($alerts, 'info', 'Fatigue élevée ('.$currentDayFatigue.'/10) pendant la phase menstruelle. Adapter l\'entraînement peut être bénéfique.');
            }
            if ($currentDayPerformanceFeel !== null && $currentDayPerformanceFeel <= $menstrualThresholds['menstrual_perf_feel_max']) {
                $this->addAlert($alerts, 'info', 'Performance ressentie faible ('.$currentDayPerformanceFeel.'/10) pendant la phase menstruelle. Évaluer l\'intensité de l\'entraînement.');
            }
        }

        return $alerts;
    }

    protected function evaluateCihCphRatioAlerts(Athlete $athlete, Carbon $weekStartDate, Collection $calculatedMetrics): array
    {
        $alerts = [];

        $cihNormalized = $calculatedMetrics->where('date', $weekStartDate->toDateString())->firstWhere('type', CalculatedMetricType::CIH_NORMALIZED)?->value;
        $cph = $calculatedMetrics->where('date', $weekStartDate->toDateString())->firstWhere('type', CalculatedMetricType::CPH)?->value;

        $chargeThresholds = self::ALERT_THRESHOLDS[CalculatedMetricType::RATIO_CIH_NORMALIZED_CPH->value];

        if ($cihNormalized > 0 && $cph > 0) {
            $ratio = $cihNormalized / $cph;

            if ($ratio < $chargeThresholds['ratio_underload_threshold']) {
                $this->addAlert($alerts, 'warning', 'Sous-charge potentielle : Charge interne ('.number_format($cihNormalized, 1).') significativement inférieure au plan ('.$cph.'). Ratio: '.number_format($ratio, 2).'.');
            } elseif ($ratio > $chargeThresholds['ratio_overload_threshold']) {
                $this->addAlert($alerts, 'warning', 'Surcharge potentielle : Charge interne ('.number_format($cihNormalized, 1).') significativement supérieure au plan ('.$cph.'). Ratio: '.number_format($ratio, 2).'.');
            } else {
                $this->addAlert($alerts, 'success', 'Charge interne ('.number_format($cihNormalized, 1).') en adéquation avec le plan ('.$cph.'). Ratio: '.number_format($ratio, 2).'.');
            }
        } elseif ($cihNormalized == 0) {
            $this->addAlert($alerts, 'info', 'Pas suffisamment de données "'.MetricType::POST_SESSION_SESSION_LOAD->getLabelShort().'" enregistrées cette semaine pour calculer la CIH Normalisée.');
        } elseif ($cph == 0) {
            $this->addAlert($alerts, 'info', 'Pas de volume/intensité planifiés pour cette semaine ou CPH est à zéro. CPH: '.$cph.'.');
        }

        return $alerts;
    }

    protected function evaluateSbmAlerts(Athlete $athlete, Carbon $sevenDaysBefore, Collection $calculatedMetrics): array
    {
        $alerts = [];

        $weeklySbmMetrics = $calculatedMetrics
            ->where('type', CalculatedMetricType::SBM)
            ->where('date', '>=', $sevenDaysBefore)
            ->where('date', '<=', $sevenDaysBefore->copy()->addDays(7));

        if ($weeklySbmMetrics->isEmpty()) {
            $this->addAlert($alerts, 'info', 'Pas de données SBM pour cette semaine.');

            return $alerts;
        }

        $averageSbm = $weeklySbmMetrics->avg('value');

        if ($averageSbm !== null) {
            $sbmThresholds = self::ALERT_THRESHOLDS[CalculatedMetricType::SBM->value];
            if ($averageSbm < $sbmThresholds['average_low_threshold']) {
                $this->addAlert($alerts, 'warning', 'SBM faible sur les 7 derniers jours (moy: '.number_format($averageSbm, 1).'/10). Surveiller la récupération.');
            } elseif ($averageSbm > $sbmThresholds['average_high_threshold']) {
                $this->addAlert($alerts, 'info', 'SBM élevé sur les 7 derniers jours (moy: '.number_format($averageSbm, 1).'/10). Bonne récupération.');
            }
        } else {
            $this->addAlert($alerts, 'info', 'Pas de données SBM pour cette semaine.');
        }

        return $alerts;
    }

    protected function checkFatigueAlerts(Athlete $athlete, Collection $metrics, array &$alerts): void
    {
        $fatigueType = MetricType::MORNING_GENERAL_FATIGUE;
        $fatigueMetrics = $metrics->filter(fn ($m) => $m->metric_type === $fatigueType);
        $fatigueThresholds = self::ALERT_THRESHOLDS[$fatigueType->value];

        if ($fatigueMetrics->count() > 5 && $fatigueType->getValueColumn() !== 'note') {
            $averageFatigue7Days = $this->metricTrendsService->calculateMetricAveragesFromCollection($fatigueMetrics, $fatigueType)['averages']['Derniers 7 jours'] ?? null;
            $averageFatigue30Days = $this->metricTrendsService->calculateMetricAveragesFromCollection($fatigueMetrics, $fatigueType)['averages']['Derniers 30 jours'] ?? null;

            if ($averageFatigue7Days !== null && $averageFatigue7Days >= $fatigueThresholds['persistent_high_7d_min'] && $averageFatigue30Days >= $fatigueThresholds['persistent_high_30d_min']) {
                $this->addAlert($alerts, 'warning', 'Fatigue générale très élevée persistante (moy. '.round($averageFatigue7Days).'/10). Potentiel signe de surentraînement ou manque de récupération.');
            } elseif ($averageFatigue7Days !== null && $averageFatigue7Days >= $fatigueThresholds['elevated_7d_min'] && $averageFatigue30Days >= $fatigueThresholds['elevated_30d_min']) {
                $this->addAlert($alerts, 'info', 'Fatigue générale élevée (moy. '.round($averageFatigue7Days).'/10). Surveiller la récupération.');
            }
        }
    }

    protected function checkSleepQualityAlerts(Athlete $athlete, Collection $metrics, array &$alerts): void
    {
        $sleepType = MetricType::MORNING_SLEEP_QUALITY;
        $sleepMetrics = $metrics->filter(fn ($m) => $m->metric_type === $sleepType);
        $sleepThresholds = self::ALERT_THRESHOLDS[$sleepType->value];
        if ($sleepMetrics->count() > 5) {
            $averageSleep7Days = $this->metricTrendsService->calculateMetricAveragesFromCollection($sleepMetrics, $sleepType)['averages']['Derniers 7 jours'] ?? null;
            $averageSleep30Days = $this->metricTrendsService->calculateMetricAveragesFromCollection($sleepMetrics, $sleepType)['averages']['Derniers 30 jours'] ?? null;

            if ($averageSleep7Days !== null && $averageSleep7Days <= $sleepThresholds['persistent_low_7d_max'] && $averageSleep30Days <= $sleepThresholds['persistent_low_30d_max']) {
                $this->addAlert($alerts, 'warning', 'Qualité de sommeil très faible persistante (moy. '.round($averageSleep7Days).'/10). Peut affecter la récupération et la performance.');
            }
        }
    }

    protected function checkPainAlerts(Athlete $athlete, Collection $metrics, array &$alerts): void
    {
        $painType = MetricType::MORNING_PAIN;
        $painMetrics = $metrics->filter(fn ($m) => in_array($m->metric_type, self::PAIN_METRICS));
        $painThresholds = self::ALERT_THRESHOLDS[$painType->value];

        // Récupérer les métriques du jour le plus récent pour les douleurs déclarées
        $sortedMetrics = $painMetrics->sortByDesc('date')->filter(function ($m) {
            return $m->date->isSameDay(Carbon::today()) || $m->date->isSameDay(Carbon::yesterday());
        });
        $currentMorningPain = $sortedMetrics ? $sortedMetrics->where('metric_type', MetricType::MORNING_PAIN)->first()?->value : null;
        $currentMorningPainLocation = $sortedMetrics ? $sortedMetrics->where('metric_type', MetricType::MORNING_PAIN_LOCATION)->first()?->note : null;
        $currentPostSessionPain = $sortedMetrics ? $sortedMetrics->where('metric_type', MetricType::POST_SESSION_PAIN)->first()?->value : null;

        // Alerte de douleur matinale déclarée élevée avec localisation
        if ($currentMorningPain !== null && $currentMorningPain >= $painThresholds['declared_high_min'] && ! empty($currentMorningPainLocation)) {
            $this->addAlert($alerts, 'danger', 'Douleur matinale élevée ('.$currentMorningPain.'/10). Localisation : '.$currentMorningPainLocation.'.');
        }

        // Alerte de douleur post-session élevée
        $postSessionPainThreshold = self::ALERT_THRESHOLDS[MetricType::POST_SESSION_PAIN->value]['declared_high_min'] ?? null;
        if ($currentPostSessionPain !== null && $postSessionPainThreshold !== null && $currentPostSessionPain >= $postSessionPainThreshold) {
            $this->addAlert($alerts, 'danger', 'Douleur post-session élevée ('.$currentPostSessionPain.'/10).');
        }

        // Logique existante pour les tendances et moyennes de douleur matinale
        if ($painMetrics->count() > 5) {
            $averagePain7Days = $this->metricTrendsService->calculateMetricAveragesFromCollection($painMetrics, $painType)['averages']['Derniers 7 jours'] ?? null;
            if ($averagePain7Days !== null && $averagePain7Days >= $painThresholds['persistent_high_7d_min']) {
                $message = 'Douleurs musculaires/articulaires persistantes (moy. '.round($averagePain7Days).'/10).';
                if (! empty($currentMorningPainLocation)) {
                    $message .= ' Localisation : '.$currentMorningPainLocation.'.';
                }
                $message .= ' Évaluer la cause et la nécessité d\'un repos.';
                $this->addAlert($alerts, 'warning', $message);
            }
        }
    }

    /**
     * Calcule la moyenne et l'écart-type d'une métrique sur une période donnée.
     *
     * @param  Collection<int, \App\Models\Metric>  $metrics  Collection de toutes les métriques de l'athlète.
     * @param  MetricType  $metricType  Le type de métrique à analyser.
     * @param  int  $days  Le nombre de jours pour la période d'analyse.
     * @return array{mean: ?float, stdDev: ?float} Retourne un tableau associatif avec la moyenne et l'écart-type,
     *                                             ou null si pas assez de données.
     */
    private function calculateMeanAndStdDev(Collection $metrics, MetricType $metricType, int $days): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        $filteredMetrics = $metrics->filter(function ($metric) use ($metricType, $startDate) {
            return $metric->metric_type === $metricType && $metric->date->greaterThanOrEqualTo($startDate);
        })->pluck('value');

        if ($filteredMetrics->count() < 5) { // Minimum 5 data points for reliable stats
            return ['mean' => null, 'stdDev' => null];
        }

        $mean = $filteredMetrics->avg();
        $sumOfSquares = $filteredMetrics->map(fn ($value) => ($value - $mean) ** 2)->sum();
        $stdDev = sqrt($sumOfSquares / ($filteredMetrics->count() - 1)); // Sample standard deviation

        return ['mean' => $mean, 'stdDev' => $stdDev];
    }

    /**
     * Effectue l'analyse du Score Z pour une métrique donnée et ajoute des alertes si les seuils sont dépassés.
     *
     * @param  Athlete  $athlete  L'athlète concerné.
     * @param  Collection<int, \App\Models\Metric>  $metrics  Collection de toutes les métriques de l'athlète.
     * @param  MetricType  $metricType  Le type de métrique à analyser.
     * @param  array  $alerts  Tableau des alertes à modifier par référence.
     */
    protected function checkZScoreAlerts(Athlete $athlete, Collection $metrics, MetricType $metricType, array &$alerts): void
    {
        $latestMetric = $metrics->where('metric_type', $metricType)->sortByDesc('date')->first();

        if (! $latestMetric) {
            return; // No recent data for this metric
        }

        $analysis = $this->calculateMeanAndStdDev($metrics, $metricType, 30); // 30-day period

        if ($analysis['mean'] === null || $analysis['stdDev'] === null || $analysis['stdDev'] == 0) {
            // Not enough data or no variability to calculate Z-score meaningfully
            // Optional: add an info alert for insufficient data if deemed necessary
            return;
        }

        $currentValue = $latestMetric->value;
        $zScore = ($currentValue - $analysis['mean']) / $analysis['stdDev'];
        $average = $analysis['mean'];
        $thresholds = self::ALERT_THRESHOLDS[$metricType->value] ?? [];
        $highCritical = $thresholds['z_score_high'] ?? 2.0;
        $lowCritical = $thresholds['z_score_low'] ?? -2.0;
        $highVigilance = 1.5;
        $lowVigilance = -1.5;

        $currentValueFormatted = number_format($currentValue, $metricType->getPrecision()).($metricType->getScale() ? '/'.$metricType->getScale() : '');

        if ($zScore >= $highCritical) {
            $this->addAlert(
                $alerts,
                $metricType->getTrendOptimalDirection() == 'bad' ? 'danger' : 'success',
                $metricType->getLabel().' ('.$currentValueFormatted.') a significativement augmenté (moy: '.number_format($average, 2).').'
            );
        } elseif ($zScore >= $highVigilance) {
            $this->addAlert(
                $alerts,
                $metricType->getTrendOptimalDirection() == 'bad' ? 'warning' : 'success',
                $metricType->getLabel().' a augmenté ('.$currentValueFormatted.').',
            );
        }

        if ($zScore <= $lowCritical) {
            $this->addAlert(
                $alerts,
                $metricType->getTrendOptimalDirection() == 'good' ? 'danger' : 'success',
                $metricType->getLabel().' ('.$currentValueFormatted.') a significativement baissé (moy: '.number_format($average, 2).').'
            );
        } elseif ($zScore <= $lowVigilance) {
            $this->addAlert(
                $alerts,
                $metricType->getTrendOptimalDirection() == 'good' ? 'warning' : 'success',
                $metricType->getLabel().' a diminué ('.$currentValueFormatted.').',
            );
        }
    }

    protected function addAlert(array &$alerts, string $type, string $message): void
    {
        $alerts[] = ['type' => $type, 'message' => $message];
    }

    /**
     * Point d'entrée simplifié pour le ReportService pour vérifier toutes les alertes pertinentes pour une journée.
     */
    public function checkAllAlerts(Athlete $athlete, Collection $dailyMetrics): array
    {
        $athletePlanWeeks = $athlete->currentTrainingPlan?->weeks ?? collect();

        // Définir les types d'alertes à inclure pour une vérification quotidienne
        $options = [
            'include_alerts' => ['general', 'charge', 'readiness', 'menstrual'],
        ];

        // Utiliser la méthode getAlerts existante avec les métriques quotidiennes et le plan de la semaine
        return $this->getAlerts($athlete, $dailyMetrics, $athletePlanWeeks, $options);
    }
}
