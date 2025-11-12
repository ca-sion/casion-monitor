<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Athlete;
use Carbon\CarbonPeriod;
use App\Enums\MetricType;
use App\Models\TrainingPlanWeek;
use Illuminate\Support\Collection;

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
        MetricType::MORNING_GENERAL_FATIGUE->value => [
            'persistent_high_7d_min'  => 7,
            'persistent_high_30d_min' => 6,
            'elevated_7d_min'         => 5,
            'elevated_30d_min'        => 5,
            'trend_increase_percent'  => 15,
        ],
        MetricType::MORNING_SLEEP_QUALITY->value => [
            'persistent_low_7d_max'  => 4,
            'persistent_low_30d_max' => 5,
            'trend_decrease_percent' => -15,
        ],
        MetricType::MORNING_PAIN->value => [
            'persistent_high_7d_min' => 5,
            'trend_increase_percent' => 20,
            'declared_high_min'      => 4,
        ],
        MetricType::POST_SESSION_PAIN->value => [
            'declared_high_min' => 4,
        ],
        MetricType::MORNING_HRV->value => [
            'trend_decrease_percent' => -10,
        ],
        MetricType::POST_SESSION_PERFORMANCE_FEEL->value => [
            'trend_decrease_percent' => -15,
        ],
        MetricType::MORNING_BODY_WEIGHT_KG->value => [
            'trend_decrease_percent' => -3,
        ],
        'CHARGE_LOAD' => [
            'ratio_underload_threshold' => 0.8,
            'ratio_overload_threshold'  => 1.3,
        ],
        'SBM' => [
            'average_low_threshold'  => 4.0,
            'average_high_threshold' => 7.5,
            'trend_decrease_percent' => -10.0,
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

        if (in_array('general', $includeAlerts)) {
            $allAlerts = array_merge($allAlerts, $this->getGeneralAlerts($athlete, $athleteMetrics));
        }

        if (in_array('charge', $includeAlerts)) {
            $allAlerts = array_merge($allAlerts, $this->getChargeAlerts($athlete, $athleteMetrics, $athletePlanWeeks));
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

        $this->checkFatigueAlerts($metrics, $alerts);
        $this->checkSleepQualityAlerts($metrics, $alerts);
        $this->checkPainAlerts($metrics, $alerts);
        $this->checkHrvAlerts($metrics, $alerts);
        $this->checkPerformanceFeelAlerts($metrics, $alerts);
        $this->checkWeightAlerts($metrics, $alerts);

        // Gérer les cas où aucune alerte spécifique n'a été détectée.
        // On vérifie d'abord s'il y a suffisamment de données pour une analyse.
        if ($metrics->isEmpty() || $metrics->count() < 5) {
            $this->addAlert($alerts, 'info', 'Pas encore suffisamment de données enregistrées pour une analyse complète.');
        }

        return $alerts;
    }

    protected function getChargeAlerts(Athlete $athlete, Collection $athleteMetrics, Collection $athletePlanWeeks): array
    {
        $alerts = [];
        $weekStartDate = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $trainingPlanWeek = $athletePlanWeeks->firstWhere('start_date', $weekStartDate);

        $alerts = array_merge($alerts, $this->evaluateCihCphRatioAlerts($athlete, $weekStartDate, $trainingPlanWeek, $athleteMetrics));
        $alerts = array_merge($alerts, $this->evaluateWeeklySbmAlerts($athlete, $weekStartDate, $athleteMetrics));
        $alerts = array_merge($alerts, $this->analyzeLongTermTrendsForAlerts($athlete, $weekStartDate, $athleteMetrics));

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

    /**
     * Analyse les métriques de charge (CIH/CPH) et génère des alertes.
     *
     * @param  Athlete  $athlete  L'athlète concerné.
     * @param  Carbon  $weekStartDate  La date de début de la semaine.
     * @param  TrainingPlanWeek|null  $trainingPlanWeek  La semaine du plan d'entraînement.
     * @param  Collection|null  $allMetrics  Collection de toutes les métriques de l'athlète (optionnel, pour optimisation).
     * @return array Un tableau d'alertes liées à la charge.
     */
    protected function evaluateCihCphRatioAlerts(Athlete $athlete, Carbon $weekStartDate, ?TrainingPlanWeek $trainingPlanWeek, ?Collection $allMetrics = null): array
    {
        $alerts = [];
        $metricsToAnalyze = $allMetrics ?? $athlete->metrics()->get();
        $cihNormalized = $this->metricCalculationService->calculateCihNormalizedForCollection($metricsToAnalyze->whereBetween('date', [$weekStartDate, $weekStartDate->copy()->endOfWeek(Carbon::SUNDAY)]));
        $cph = $trainingPlanWeek ? $this->metricCalculationService->calculateCph($trainingPlanWeek) : 0.0;

        $chargeThresholds = self::ALERT_THRESHOLDS['CHARGE_LOAD'];

        if ($cihNormalized > 0 && $cph > 0) {
            $ratio = $this->metricCalculationService->calculateRatio($cihNormalized, $cph);

            if ($ratio < $chargeThresholds['ratio_underload_threshold']) {
                $this->addAlert($alerts, 'warning', 'Sous-charge potentielle : Charge interne ('.number_format($cihNormalized, 1).') significativement inférieure au plan ('.$cph.'). Ratio: '.number_format($ratio, 2).'.');
            } elseif ($ratio > $chargeThresholds['ratio_overload_threshold']) {
                $this->addAlert($alerts, 'warning', 'Surcharge potentielle : Charge interne ('.number_format($cihNormalized, 1).') significativement supérieure au plan ('.$cph.'). Ratio: '.number_format($ratio, 2).'.');
            } else {
                $this->addAlert($alerts, 'success', 'Charge interne ('.number_format($cihNormalized, 1).') en adéquation avec le plan ('.$cph.'). Ratio: '.number_format($ratio, 2).'.');
            }
        } elseif ($cihNormalized == 0) {
            $this->addAlert($alerts, 'info', 'Pas suffisamment de données "'.MetricType::POST_SESSION_SESSION_LOAD->getLabelShort().'" enregistrées cette semaine pour calculer le CIH Normalisée.');
        } elseif ($cph == 0) {
            $this->addAlert($alerts, 'info', 'Pas de volume/intensité planifiés pour cette semaine ou CPH est à zéro. CPH: '.$cph.'.');
        }

        return $alerts;
    }

    /**
     * Analyse les métriques SBM et génère des alertes.
     *
     * @param  Athlete  $athlete  L'athlète concerné.
     * @param  Carbon  $weekStartDate  La date de début de la semaine.
     * @param  Collection|null  $allMetrics  Collection de toutes les métriques de l'athlète (optionnel, pour optimisation).
     * @return array Un tableau d'alertes liées au SBM.
     */
    protected function evaluateWeeklySbmAlerts(Athlete $athlete, Carbon $weekStartDate, ?Collection $allMetrics = null): array
    {
        $alerts = [];
        $sbmSum = 0;
        $sbmCount = 0;
        $period = CarbonPeriod::create($weekStartDate, '1 day', $weekStartDate->copy()->endOfWeek(Carbon::SUNDAY));
        foreach ($period as $date) {
            if (is_null($allMetrics)) {
                $sbmValue = $this->metricCalculationService->calculateSbm($athlete, $date);
            } else {
                $dailyMetrics = $allMetrics->filter(fn ($m) => $m->date->format('Y-m-d') === $date->format('Y-m-d'));
                $sbmValue = $this->metricCalculationService->calculateSbmForCollection($dailyMetrics);
            }

            if ($sbmValue !== null) {
                $sbmSum += $sbmValue;
                $sbmCount++;
            }
        }
        $averageSbm = $sbmCount > 0 ? $sbmSum / $sbmCount : null;

        if ($averageSbm !== null) {
            $sbmThresholds = self::ALERT_THRESHOLDS['SBM'];
            if ($averageSbm < $sbmThresholds['average_low_threshold']) {
                $this->addAlert($alerts, 'warning', 'SBM faible pour la semaine (moy: '.number_format($averageSbm, 1).'/10). Surveiller la récupération.');
            } elseif ($averageSbm > $sbmThresholds['average_high_threshold']) {
                $this->addAlert($alerts, 'info', 'SBM élevé pour la semaine (moy: '.number_format($averageSbm, 1).'/10). Bonne récupération.');
            }
        } else {
            $this->addAlert($alerts, 'info', 'Pas de données SBM pour cette semaine.');
        }

        return $alerts;
    }

    /**
     * Analyse les tendances SBM et VFC sur plusieurs semaines et génère des alertes.
     *
     * @param  Athlete  $athlete  L'athlète concerné.
     * @param  Carbon  $currentWeekStartDate  La date de début de la semaine actuelle.
     * @param  Collection|null  $allMetrics  Collection de toutes les métriques de l'athlète (optionnel, pour optimisation).
     * @return array Un tableau d'alertes liées aux tendances SBM et VFC.
     */
    protected function analyzeLongTermTrendsForAlerts(Athlete $athlete, Carbon $currentWeekStartDate, ?Collection $allMetrics = null): array
    {
        $alerts = [];

        $period = 'last_30_days';
        $startDate = Carbon::now()->subDays(30)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $sbmDataCollection = new Collection;
        $currentDate = $startDate->copy();
        while ($currentDate->lessThanOrEqualTo($endDate)) {
            if (is_null($allMetrics)) {
                $sbmValue = $this->metricCalculationService->calculateSbm($athlete, $currentDate);
            } else {
                $dailyMetrics = $allMetrics->filter(fn ($m) => $m->date->format('Y-m-d') === $currentDate->format('Y-m-d'));
                $sbmValue = $this->metricCalculationService->calculateSbmForCollection($dailyMetrics);
            }

            if ($sbmValue !== null && $sbmValue !== 0.0) {
                $sbmDataCollection->push((object) ['date' => $currentDate->copy(), 'value' => $sbmValue]);
            }
            $currentDate->addDay();
        }

        if ($sbmDataCollection->count() > 5) {
            $sbmThresholds = self::ALERT_THRESHOLDS['SBM'];
            $sbmTrend = $this->metricTrendsService->calculateGenericNumericTrend($sbmDataCollection);
            if ($sbmTrend['trend'] === 'decreasing' && $sbmTrend['change'] < $sbmThresholds['trend_decrease_percent']) {
                $this->addAlert($alerts, 'warning', 'Baisse significative du SBM ('.number_format($sbmTrend['change'], 1).'%) sur les 30 derniers jours.');
            }
        }

        $hrvMetrics = $allMetrics->filter(fn ($m) => $m->metric_type === MetricType::MORNING_HRV);

        if ($hrvMetrics->count() > 5) {
            $hrvTrend = $this->metricTrendsService->calculateMetricEvolutionTrend($hrvMetrics, MetricType::MORNING_HRV);
            $hrvThresholds = self::ALERT_THRESHOLDS[MetricType::MORNING_HRV->value];
            if ($hrvTrend['trend'] === 'decreasing' && $hrvTrend['change'] < $hrvThresholds['trend_decrease_percent']) {
                $this->addAlert($alerts, 'warning', 'Diminution significative de la VFC ('.number_format($hrvTrend['change'], 1).'%) sur les 30 derniers jours.');
            }
        }

        return $alerts;
    }

    protected function checkFatigueAlerts(Collection $metrics, array &$alerts): void
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
            $fatigueTrend = $this->metricTrendsService->calculateMetricEvolutionTrend($fatigueMetrics, $fatigueType);
            if ($fatigueTrend['trend'] === 'increasing' && $fatigueTrend['change'] > $fatigueThresholds['trend_increase_percent']) {
                $this->addAlert($alerts, 'warning', 'Augmentation significative de la fatigue générale (+'.number_format($fatigueTrend['change'], 1).'%).');
            }
        }
    }

    protected function checkSleepQualityAlerts(Collection $metrics, array &$alerts): void
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
            $sleepTrend = $this->metricTrendsService->calculateMetricEvolutionTrend($sleepMetrics, $sleepType);
            if ($sleepTrend['trend'] === 'decreasing' && $sleepTrend['change'] < $sleepThresholds['trend_decrease_percent']) {
                $this->addAlert($alerts, 'warning', 'Diminution significative de la qualité du sommeil ('.number_format($sleepTrend['change'], 1).'%).');
            }
        }
    }

    protected function checkPainAlerts(Collection $metrics, array &$alerts): void
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
            $painTrend = $this->metricTrendsService->calculateMetricEvolutionTrend($painMetrics, $painType);
            if ($painTrend['trend'] === 'increasing' && $painTrend['change'] > $painThresholds['trend_increase_percent']) {
                $this->addAlert($alerts, 'warning', 'Augmentation significative des douleurs (+'.number_format($painTrend['change'], 1).'%).');
            }
        }
    }

    protected function checkHrvAlerts(Collection $metrics, array &$alerts): void
    {
        $hrvType = MetricType::MORNING_HRV;
        $hrvMetrics = $metrics->filter(fn ($m) => $m->metric_type === $hrvType);
        $hrvThresholds = self::ALERT_THRESHOLDS[$hrvType->value];
        if ($hrvMetrics->count() > 5) {
            $hrvTrend = $this->metricTrendsService->calculateMetricEvolutionTrend($hrvMetrics, $hrvType);
            if ($hrvTrend['trend'] === 'decreasing' && $hrvTrend['change'] < $hrvThresholds['trend_decrease_percent']) {
                $this->addAlert($alerts, 'warning', 'Diminution significative de la VFC ('.number_format($hrvTrend['change'], 1).'%). Peut indiquer un stress ou une fatigue accrue.');
            }
        }
    }

    protected function checkPerformanceFeelAlerts(Collection $metrics, array &$alerts): void
    {
        $perfFeelType = MetricType::POST_SESSION_PERFORMANCE_FEEL;
        $perfFeelMetrics = $metrics->filter(fn ($m) => $m->metric_type === $perfFeelType);
        $perfFeelThresholds = self::ALERT_THRESHOLDS[$perfFeelType->value];
        if ($perfFeelMetrics->count() > 5) {
            $perfFeelTrend = $this->metricTrendsService->calculateMetricEvolutionTrend($perfFeelMetrics, $perfFeelType);
            if ($perfFeelTrend['trend'] === 'decreasing' && $perfFeelTrend['change'] < $perfFeelThresholds['trend_decrease_percent']) {
                $this->addAlert($alerts, 'warning', 'Diminution significative du ressenti de performance en séance ('.number_format($perfFeelTrend['change'], 1).'%).');
            }
        }
    }

    protected function checkWeightAlerts(Collection $metrics, array &$alerts): void
    {
        $weightType = MetricType::MORNING_BODY_WEIGHT_KG;
        $weightMetrics = $metrics->filter(fn ($m) => $m->metric_type === $weightType);
        $weightThresholds = self::ALERT_THRESHOLDS[$weightType->value];
        if ($weightMetrics->count() > 5) {
            $weightTrend = $this->metricTrendsService->calculateMetricEvolutionTrend($weightMetrics, $weightType);
            if ($weightTrend['trend'] === 'decreasing' && $weightTrend['change'] < $weightThresholds['trend_decrease_percent']) {
                $this->addAlert($alerts, 'warning', 'Perte de poids significative ('.number_format(abs($weightTrend['change']), 1).'%). Peut être un signe de déficit énergétique.');
            }
        }
    }

    protected function addAlert(array &$alerts, string $type, string $message): void
    {
        $alerts[] = ['type' => $type, 'message' => $message];
    }

    /**
     * Point d'entrée simplifié pour le ReportService pour vérifier toutes les alertes pertinentes pour une journée.
     *
     * @param Athlete $athlete
     * @param Collection $dailyMetrics
     * @return array
     */
    public function checkAllAlerts(Athlete $athlete, Collection $dailyMetrics): array
    {
        $currentWeekStartDate = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $athletePlanWeeks = $athlete->currentTrainingPlan?->weeks ?? collect();
        
        // Définir les types d'alertes à inclure pour une vérification quotidienne
        $options = [
            'include_alerts' => ['general', 'charge', 'readiness', 'menstrual'],
        ];

        // Utiliser la méthode getAlerts existante avec les métriques quotidiennes et le plan de la semaine
        return $this->getAlerts($athlete, $dailyMetrics, $athletePlanWeeks, $options);
    }
}
