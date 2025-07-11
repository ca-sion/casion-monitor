<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Athlete;
use Carbon\CarbonPeriod;
use App\Enums\MetricType;
use App\Models\TrainingPlanWeek;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MetricAlertsService
{
    protected MetricCalculationService $metricCalculationService;

    protected MetricTrendsService $metricTrendsService;

    protected MetricReadinessService $metricReadinessService;

    protected MetricMenstrualService $metricMenstrualService;

    public function __construct(MetricCalculationService $metricCalculationService , MetricTrendsService $metricTrendsService, MetricReadinessService $metricReadinessService, MetricMenstrualService $metricMenstrualService)
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
        'CIH_NORMALIZED' => [
            'normalization_days' => 4,
        ],
        'READINESS_SCORE' => [
            'sbm_penalty_factor'             => 5,
            'hrv_drop_severe_percent'        => -10,
            'hrv_drop_moderate_percent'      => -5,
            'pain_penalty_factor'            => 4,
            'pre_session_energy_low'         => 4,
            'pre_session_energy_medium'      => 6,
            'pre_session_penalty_high'       => 15,
            'pre_session_penalty_medium'     => 5,
            'pre_session_leg_feel_low'       => 4,
            'pre_session_leg_feel_medium'    => 6,
            'charge_overload_penalty_factor' => 15,
            'charge_overload_threshold'      => 1.3,
            'level_red_threshold'            => 50,
            'level_orange_threshold'         => 70,
            'level_yellow_threshold'         => 85,
            'severe_pain_threshold'          => 7,
            'menstrual_energy_low'           => 4,
        ],
    ];

    public function getAlerts(Athlete $athlete, Collection $athleteMetrics, Collection $athletePlanWeeks, string $period, array $options): array
    {
        $allAlerts = [];
        $includeAlerts = $options['include_alerts'] ?? [];

        if (in_array('general', $includeAlerts)) {
            $allAlerts = array_merge($allAlerts, $this->getGeneralAlerts($athlete, $athleteMetrics, $period));
        }

        if (in_array('charge', $includeAlerts)) {
            $allAlerts = array_merge($allAlerts, $this->getChargeAlerts($athlete, $athleteMetrics, $athletePlanWeeks, $period));
        }

        if (in_array('readiness', $includeAlerts)) {
            $allAlerts = array_merge($allAlerts, $this->getReadinessAlerts($athlete, $athleteMetrics));
        }

        if (in_array('menstrual', $includeAlerts) && $athlete->gender->value === 'w') {
            $allAlerts = array_merge($allAlerts, $this->getMenstrualAlerts($athlete, $athleteMetrics));
        }

        return $allAlerts;
    }

    protected function getGeneralAlerts(Athlete $athlete, Collection $metrics, string $period): array
    {
        $alerts = [];

        // 1. Fatigue générale persistante (MORNING_GENERAL_FATIGUE)
        $fatigueType = MetricType::MORNING_GENERAL_FATIGUE;
        $fatigueMetrics = $metrics->filter(fn ($m) => $m->metric_type === $fatigueType);
        $fatigueThresholds = self::ALERT_THRESHOLDS[$fatigueType->value];
        
        if ($fatigueMetrics->count() > 5 && $fatigueType->getValueColumn() !== 'note') {
            $averageFatigue7Days = $this->metricTrendsService->getMetricTrendsForCollection($fatigueMetrics, MetricType::MORNING_GENERAL_FATIGUE)['averages']['Derniers 7 jours'] ?? null;
            $averageFatigue30Days = $this->metricTrendsService->getMetricTrendsForCollection($fatigueMetrics, MetricType::MORNING_GENERAL_FATIGUE)['averages']['Derniers 30 jours'] ?? null;

            if ($averageFatigue7Days !== null && $averageFatigue7Days >= $fatigueThresholds['persistent_high_7d_min'] && $averageFatigue30Days >= $fatigueThresholds['persistent_high_30d_min']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Fatigue générale très élevée persistante (moy. 7j: '.round($averageFatigue7Days).'/10). Potentiel signe de surentraînement ou manque de récupération.'];
            } elseif ($averageFatigue7Days !== null && $averageFatigue7Days >= $fatigueThresholds['elevated_7d_min'] && $averageFatigue30Days >= $fatigueThresholds['elevated_30d_min']) {
                $alerts[] = ['type' => 'info', 'message' => 'Fatigue générale élevée (moy. 7j: '.round($averageFatigue7Days).'/10). Surveiller la récupération.'];
            }
            $fatigueTrend = $this->metricTrendsService->getEvolutionTrendForCollection($fatigueMetrics, MetricType::MORNING_GENERAL_FATIGUE);
            if ($fatigueTrend['trend'] === 'increasing' && $fatigueTrend['change'] > $fatigueThresholds['trend_increase_percent']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Augmentation significative de la fatigue générale (+'.number_format($fatigueTrend['change'], 1).'%).'];
            }
        }

        // 2. Diminution de la qualité du sommeil (MORNING_SLEEP_QUALITY)
        $sleepMetrics = $metrics->filter(fn ($m) => $m->metric_type === MetricType::MORNING_SLEEP_QUALITY);
        $sleepThresholds = self::ALERT_THRESHOLDS[MetricType::MORNING_SLEEP_QUALITY->value];
        if ($sleepMetrics->count() > 5) {
            $averageSleep7Days = $this->metricTrendsService->getMetricTrendsForCollection($sleepMetrics, MetricType::MORNING_SLEEP_QUALITY)['averages']['Derniers 7 jours'] ?? null;
            $averageSleep30Days = $this->metricTrendsService->getMetricTrendsForCollection($sleepMetrics, MetricType::MORNING_SLEEP_QUALITY)['averages']['Derniers 30 jours'] ?? null;

            if ($averageSleep7Days !== null && $averageSleep7Days <= $sleepThresholds['persistent_low_7d_max'] && $averageSleep30Days <= $sleepThresholds['persistent_low_30d_max']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Qualité de sommeil très faible persistante (moy. 7j: '.round($averageSleep7Days).'/10). Peut affecter la récupération et la performance.'];
            }
            $sleepTrend = $this->metricTrendsService->getEvolutionTrendForCollection($sleepMetrics, MetricType::MORNING_SLEEP_QUALITY);
            if ($sleepTrend['trend'] === 'decreasing' && $sleepTrend['change'] < $sleepThresholds['trend_decrease_percent']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Diminution significative de la qualité du sommeil ('.number_format($sleepTrend['change'], 1).'%).'];
            }
        }

        // 3. Douleurs persistantes (MORNING_PAIN)
        $painMetrics = $metrics->filter(fn ($m) => $m->metric_type === MetricType::MORNING_PAIN);
        $painThresholds = self::ALERT_THRESHOLDS[MetricType::MORNING_PAIN->value];
        if ($painMetrics->count() > 5) {
            $averagePain7Days = $this->metricTrendsService->getMetricTrendsForCollection($painMetrics, MetricType::MORNING_PAIN)['averages']['Derniers 7 jours'] ?? null;
            if ($averagePain7Days !== null && $averagePain7Days >= $painThresholds['persistent_high_7d_min']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Douleurs musculaires/articulaires persistantes (moy. 7j: '.round($averagePain7Days)."/10). Évaluer la cause et la nécessité d'un repos."];
            }
            $painTrend = $this->metricTrendsService->getEvolutionTrendForCollection($painMetrics, MetricType::MORNING_PAIN);
            if ($painTrend['trend'] === 'increasing' && $painTrend['change'] > $painThresholds['trend_increase_percent']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Augmentation significative des douleurs (+'.number_format($painTrend['change'], 1).'%).'];
            }
        }

        // 4. Baisse de la VFC (MORNING_HRV) - indicateur de stress ou de fatigue
        $hrvMetrics = $metrics->filter(fn ($m) => $m->metric_type === MetricType::MORNING_HRV);
        $hrvThresholds = self::ALERT_THRESHOLDS[MetricType::MORNING_HRV->value];
        if ($hrvMetrics->count() > 5) {
            $hrvTrend = $this->metricTrendsService->getEvolutionTrendForCollection($hrvMetrics, MetricType::MORNING_HRV);
            if ($hrvTrend['trend'] === 'decreasing' && $hrvTrend['change'] < $hrvThresholds['trend_decrease_percent']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Diminution significative de la VFC ('.number_format($hrvTrend['change'], 1).'%). Peut indiquer un stress ou une fatigue accrue.'];
            }
        }

        // 5. Baisse du ressenti de performance (POST_SESSION_PERFORMANCE_FEEL)
        $perfFeelMetrics = $metrics->filter(fn ($m) => $m->metric_type === MetricType::POST_SESSION_PERFORMANCE_FEEL);
        $perfFeelThresholds = self::ALERT_THRESHOLDS[MetricType::POST_SESSION_PERFORMANCE_FEEL->value];
        if ($perfFeelMetrics->count() > 5) {
            $perfFeelTrend = $this->metricTrendsService->getEvolutionTrendForCollection($perfFeelMetrics, MetricType::POST_SESSION_PERFORMANCE_FEEL);
            if ($perfFeelTrend['trend'] === 'decreasing' && $perfFeelTrend['change'] < $perfFeelThresholds['trend_decrease_percent']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Diminution significative du ressenti de performance en séance ('.number_format($perfFeelTrend['change'], 1).'%).'];
            }
        }

        // 6. Faible poids corporel ou perte de poids rapide (MORNING_BODY_WEIGHT_KG)
        $weightMetrics = $metrics->filter(fn ($m) => $m->metric_type === MetricType::MORNING_BODY_WEIGHT_KG);
        $weightThresholds = self::ALERT_THRESHOLDS[MetricType::MORNING_BODY_WEIGHT_KG->value];
        if ($weightMetrics->count() > 5) {
            $weightTrend = $this->metricTrendsService->getEvolutionTrendForCollection($weightMetrics, MetricType::MORNING_BODY_WEIGHT_KG);
            if ($weightTrend['trend'] === 'decreasing' && $weightTrend['change'] < $weightThresholds['trend_decrease_percent']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Perte de poids significative ('.number_format(abs($weightTrend['change']), 1).'%). Peut être un signe de déficit énergétique.'];
            }
        }

        // Gérer les cas où aucune alerte spécifique n'a été détectée.
        // On vérifie d'abord s'il y a suffisamment de données pour une analyse.
        if ($metrics->isEmpty() || $metrics->count() < 5) {
            $alerts[] = ['type' => 'info', 'message' => 'Pas encore suffisamment de données enregistrées pour une analyse complète sur la période : '.str_replace('_', ' ', $period).'.'];
        }

        return $alerts;
    }

    protected function getChargeAlerts(Athlete $athlete, Collection $athleteMetrics, Collection $athletePlanWeeks, string $period): array
    {
        $alerts = [];
        $weekStartDate = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $trainingPlanWeek = $athletePlanWeeks->firstWhere('start_date', $weekStartDate->toDateString());

        $alerts = array_merge($alerts, $this->analyzeChargeMetrics($athlete, $weekStartDate, $trainingPlanWeek, $athleteMetrics));
        $alerts = array_merge($alerts, $this->analyzeSbmMetrics($athlete, $weekStartDate, $athleteMetrics));
        $alerts = array_merge($alerts, $this->analyzeMultiWeekTrends($athlete, $weekStartDate, $athleteMetrics));

        return $alerts;
    }

    protected function getReadinessAlerts(Athlete $athlete, Collection $athleteMetrics): array
    {
        $alerts = [];
        $readinessStatus = $this->metricReadinessService->getAthleteReadinessStatus($athlete, $athleteMetrics);
        if ($readinessStatus['level'] !== 'green') {
            $alerts[] = ['type' => $readinessStatus['level'], 'message' => $readinessStatus['message']];
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
            $alerts[] = [
                'type'    => 'danger',
                'message' => "Cycle menstruel irrégulier (moy. {$cycleData['cycle_length_avg']} jours). Il est suggéré de consulter pour évaluer un potentiel RED-S.",
            ];
        }
        // 2. Absence de règles prolongée sans données de cycle moyen (cas où 'deduceMenstrualCyclePhase' n'aurait pas pu déterminer la phase 'Aménorrhée' faute de données de moyenne)
        elseif ($cycleData['cycle_length_avg'] === null && $cycleData['last_period_start']) {
            $daysSinceLastPeriod = Carbon::parse($cycleData['last_period_start'])->diffInDays(Carbon::now());
            if ($daysSinceLastPeriod > $menstrualThresholds['prolonged_absence_no_avg']) {
                $alerts[] = ['type' => 'danger', 'message' => 'Absence de règles prolongée ('.$daysSinceLastPeriod.' jours depuis les dernières règles). Forte suspicion de RED-S. Consultation médicale impérative.'];
            }
        }
        // 3. Potentiel retard ou cycle long avec une moyenne de cycle NORMAL (21-35 jours)
        elseif ($cycleData['phase'] === 'Potentiel retard ou cycle long'
            && $cycleData['cycle_length_avg'] >= $menstrualThresholds['oligomenorrhea_min_cycle']
            && $cycleData['cycle_length_avg'] <= $menstrualThresholds['oligomenorrhea_max_cycle']) {
            $alerts[] = [
                'type'    => 'warning',
                'message' => "Retard du cycle menstruel (moy. {$cycleData['cycle_length_avg']} jours). Suggéré de surveiller.",
            ];
        }
        // 4. Phase 'Inconnue' en raison de l'absence de données J1 (priorité faible)
        elseif ($cycleData['phase'] === 'Inconnue' && $cycleData['reason'] === 'Enregistrez au moins deux J1 pour calculer la durée moyenne de votre cycle.') {
            $alerts[] = ['type' => 'info', 'message' => 'Aucune donnée récente sur le premier jour des règles pour cette athlète. Un suivi est recommandé.'];
        }

        // 5. Corrélation entre phase menstruelle et performance/fatigue
        if ($cycleData['phase'] === 'Menstruelle') {
            $currentDayFatigue = $athleteMetrics->firstWhere('metric_type', MetricType::MORNING_GENERAL_FATIGUE)?->value;
            $currentDayPerformanceFeel = $athleteMetrics->firstWhere('metric_type', MetricType::POST_SESSION_PERFORMANCE_FEEL)?->value;

            if ($currentDayFatigue !== null && $currentDayFatigue >= $menstrualThresholds['menstrual_fatigue_min']) {
                $alerts[] = ['type' => 'info', 'message' => 'Fatigue élevée ('.$currentDayFatigue."/10) pendant la phase menstruelle. Adapter l'entraînement peut être bénéfique."];
            }
            if ($currentDayPerformanceFeel !== null && $currentDayPerformanceFeel <= $menstrualThresholds['menstrual_perf_feel_max']) {
                $alerts[] = ['type' => 'info', 'message' => 'Performance ressentie faible ('.$currentDayPerformanceFeel."/10) pendant la phase menstruelle. Évaluer l'intensité de l'entraînement."];
            }
        }

        return $alerts;
    }

    /**
     * Récupère les alertes pour un athlète à partir d'une collection de métriques pré-chargée.
     */
    // protected function getAthleteAlertsForCollection(Athlete $athlete, Collection $metrics, string $period = 'last_60_days'): array
    // {
    //     return $this->getAthleteAlerts($athlete, $period, $metrics);
    // }

    /**
     * Déduit la phase du cycle menstruel d'un athlète féminin à partir d'une collection de métriques pré-chargée.
     */
    // protected function deduceMenstrualCyclePhaseForCollection(Athlete $athlete, Collection $allMetrics): array
    // {
    //     return $this->deduceMenstrualCyclePhase($athlete, $allMetrics);
    // }

    /**
     * Récupère les alertes de charge pour un athlète à partir de collections pré-chargées.
     */
    // protected function getChargeAlertsForCollection(Athlete $athlete, Collection $allMetrics, Collection $planWeeks, Carbon $weekStartDate): array
    // {
    //     return $this->getChargeAlerts($athlete, $weekStartDate, $allMetrics, $planWeeks);
    // }

    /**
     * Analyse les métriques de charge (CIH/CPH) et génère des alertes.
     *
     * @param  Athlete  $athlete  L'athlète concerné.
     * @param  Carbon  $weekStartDate  La date de début de la semaine.
     * @param  TrainingPlanWeek|null  $trainingPlanWeek  La semaine du plan d'entraînement.
     * @param  Collection|null  $allMetrics  Collection de toutes les métriques de l'athlète (optionnel, pour optimisation).
     * @return array Un tableau d'alertes liées à la charge.
     */
    protected function analyzeChargeMetrics(Athlete $athlete, Carbon $weekStartDate, ?TrainingPlanWeek $trainingPlanWeek, ?Collection $allMetrics = null): array
    {
        $alerts = [];
        $metricsToAnalyze = $allMetrics ?? $athlete->metrics()->get();
        $cihNormalized = $this->metricCalculationService->calculateCihNormalizedForCollection($metricsToAnalyze->whereBetween('date', [$weekStartDate, $weekStartDate->copy()->endOfWeek(Carbon::SUNDAY)]));
        $cph = $trainingPlanWeek ? $this->metricCalculationService->calculateCph($trainingPlanWeek) : 0.0;

        $chargeThresholds = self::ALERT_THRESHOLDS['CHARGE_LOAD'];

        if ($cihNormalized > 0 && $cph > 0) {
            $ratio = $this->metricCalculationService->calculateRatio($cihNormalized, $cph);

            if ($ratio < $chargeThresholds['ratio_underload_threshold']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Sous-charge potentielle : Charge interne ('.number_format($cihNormalized, 1).") significativement inférieure au plan ({$cph}). Ratio: ".number_format($ratio, 2).'.'];
            } elseif ($ratio > $chargeThresholds['ratio_overload_threshold']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Surcharge potentielle : Charge interne ('.number_format($cihNormalized, 1).") significativement supérieure au plan ({$cph}). Ratio: ".number_format($ratio, 2).'.'];
            } else {
                $alerts[] = ['type' => 'success', 'message' => 'Charge interne ('.number_format($cihNormalized, 1).") en adéquation avec le plan ({$cph}). Ratio: ".number_format($ratio, 2).'.'];
            }
        } elseif ($cihNormalized == 0) {
            $alerts[] = ['type' => 'info', 'message' => 'Pas suffisamment de données "'.MetricType::POST_SESSION_SESSION_LOAD->getLabelShort().'" enregistrées cette semaine pour calculer le CIH Normalisée.'];
        } elseif ($cph == 0) {
            $alerts[] = ['type' => 'info', 'message' => "Pas de volume/intensité planifiés pour cette semaine ou CPH est à zéro. CPH: {$cph}."];
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
    protected function analyzeSbmMetrics(Athlete $athlete, Carbon $weekStartDate, ?Collection $allMetrics = null): array
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
                $alerts[] = ['type' => 'warning', 'message' => 'SBM faible pour la semaine (moy: '.number_format($averageSbm, 1).'/10). Surveiller la récupération.'];
            } elseif ($averageSbm > $sbmThresholds['average_high_threshold']) {
                $alerts[] = ['type' => 'info', 'message' => 'SBM élevé pour la semaine (moy: '.number_format($averageSbm, 1).'/10). Bonne récupération.'];
            }
        } else {
            $alerts[] = ['type' => 'info', 'message' => 'Pas de données SBM pour cette semaine.'];
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
    protected function analyzeMultiWeekTrends(Athlete $athlete, Carbon $currentWeekStartDate, ?Collection $allMetrics = null): array
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
            $sbmTrend = $this->metricTrendsService->calculateTrendFromNumericCollection($sbmDataCollection);
            if ($sbmTrend['trend'] === 'decreasing' && $sbmTrend['change'] < $sbmThresholds['trend_decrease_percent']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Baisse significative du SBM ('.number_format($sbmTrend['change'], 1).'%) sur les 30 derniers jours.'];
            }
        }

        $hrvMetrics = $allMetrics->filter(fn ($m) => $m->metric_type === MetricType::MORNING_HRV);

        if ($hrvMetrics->count() > 5) {
            $hrvTrend = $this->metricTrendsService->getEvolutionTrendForCollection($hrvMetrics, MetricType::MORNING_HRV);
            $hrvThresholds = self::ALERT_THRESHOLDS[MetricType::MORNING_HRV->value];
            if ($hrvTrend['trend'] === 'decreasing' && $hrvTrend['change'] < $hrvThresholds['trend_decrease_percent']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Diminution significative de la VFC ('.number_format($hrvTrend['change'], 1).'%) sur les 30 derniers jours.'];
            }
        }

        return $alerts;
    }
}
