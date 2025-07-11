<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Metric;
use App\Models\Athlete;
use Carbon\CarbonPeriod;
use App\Enums\MetricType;
use App\Enums\CalculatedMetric;
use App\Models\TrainingPlanWeek;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class MetricStatisticsService
{
    protected MetricCalculationService $metricCalculationService;

    protected MetricAlertsService $metricAlertsService;

    protected MetricTrendsService $metricTrendsService;

    protected MetricReadinessService $metricReadinessService;

    public function __construct(MetricCalculationService $metricCalculationService, MetricAlertsService $metricAlertsService, MetricTrendsService $metricTrendsService, MetricReadinessService $metricReadinessService)
    {
        $this->metricCalculationService = $metricCalculationService;
        $this->metricAlertsService = $metricAlertsService;
        $this->metricTrendsService = $metricTrendsService;
        $this->metricReadinessService = $metricReadinessService;
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

    private const ESSENTIAL_DAILY_READINESS_METRICS = [
        MetricType::MORNING_SLEEP_QUALITY,
        MetricType::MORNING_GENERAL_FATIGUE,
        MetricType::PRE_SESSION_ENERGY_LEVEL,
        MetricType::PRE_SESSION_LEG_FEEL,
    ];

    public function getAthletesData(Collection $athletes, array $options = []): Collection
    {
        // Options par défaut
        $defaultOptions = [
            'period'                       => 'last_60_days',
            'metric_types'                 => MetricType::cases(),
            'calculated_metrics'           => CalculatedMetric::cases(),
            'include_dashboard_metrics'    => false,
            'include_weekly_metrics'       => false,
            'include_latest_daily_metrics' => false,
            'include_alerts'               => ['general', 'charge', 'readiness'],
            'include_menstrual_cycle'      => false,
            'include_readiness_status'     => false,
        ];
        $options = array_merge($defaultOptions, $options);

        $athleteIds = $athletes->pluck('id');
        if ($athleteIds->isEmpty()) {
            return collect();
        }

        // 1. Déterminer la période maximale de récupération des métriques brutes
        $maxStartDate = $this->determineMaxStartDate($options);

        // 2. Pré-charger toutes les métriques et les semaines de plan d'entraînement
        // Si le cycle menstruel est inclus, s'assurer d'avoir 1-2 ans de données pour les métriques J1
        $allMetricsByAthlete = Metric::whereIn('athlete_id', $athleteIds)
            ->when($options['include_menstrual_cycle'], function (Builder $query) {
                $query->where('date', '>=', now()->copy()->subYears(2)->startOfDay())
                    ->where('metric_type', MetricType::MORNING_FIRST_DAY_PERIOD);
            }, function (Builder $query) use ($maxStartDate, $options) {
                $query->where('date', '>=', $maxStartDate)
                    ->whereIn('metric_type', $options['metric_types']);
            })
            ->orderBy('date', 'asc')
            ->get()
            ->groupBy('athlete_id');

        $athletesTrainingPlansIds = $athletes->pluck('current_training_plan.id')->filter()->values();
        $allTrainingPlanWeeksByAthleteTrainingPlanId = TrainingPlanWeek::whereIn('training_plan_id', $athletesTrainingPlansIds)
            ->where('start_date', '>=', $maxStartDate->copy()->startOfWeek(Carbon::MONDAY))
            ->get()
            ->groupBy('training_plan_id');

        // 3. Itérer sur chaque athlète et effectuer les calculs conditionnels
        return $athletes->map(function ($athlete) use ($allMetricsByAthlete, $allTrainingPlanWeeksByAthleteTrainingPlanId, $options) {
            $athleteMetrics = $allMetricsByAthlete->get($athlete->id) ?? collect();
            $athleteTrainingPlansId = $athlete->currentTrainingPlan?->id;
            $athletePlanWeeks = $allTrainingPlanWeeksByAthleteTrainingPlanId->get($athleteTrainingPlansId) ?? collect();

            $athleteData = []; // Données à ajouter à l'athlète

            // Filtrer les métriques par la période demandée par l'utilisateur pour les calculs
            $periodStartDate = $this->getStartDateFromPeriod($options['period']);
            $filteredAthleteMetrics = $athleteMetrics->where('date', '>=', $periodStartDate);

            // Calculs conditionnels basés sur les options
            if ($options['include_dashboard_metrics']) {
                $dashboardMetricTypes = $options['metric_types'] ?: [
                    MetricType::MORNING_HRV, MetricType::POST_SESSION_SESSION_LOAD,
                    MetricType::POST_SESSION_SUBJECTIVE_FATIGUE, MetricType::MORNING_GENERAL_FATIGUE,
                    MetricType::MORNING_SLEEP_QUALITY, MetricType::MORNING_BODY_WEIGHT_KG,
                ];
                $metricsDataForDashboard = [];
                foreach ($dashboardMetricTypes as $metricType) {
                    $metricsForType = $filteredAthleteMetrics->where('metric_type', $metricType->value);
                    $metricsDataForDashboard[$metricType->value] = $this->getDashboardMetricDataForCollection($metricsForType, $metricType, $options['period']);
                }
                $athleteData['dashboard_metrics_data'] = $metricsDataForDashboard;
            }

            if ($options['include_weekly_metrics']) {
                $weeklyMetricsData = [];
                foreach ($options['calculated_metrics'] as $calculatedMetric) {
                    if ($calculatedMetric === CalculatedMetric::READINESS_SCORE) {
                        continue; // Le score de readiness est géré séparément, pas comme une métrique hebdomadaire ici.
                    }
                    $weeklyMetricsData[$calculatedMetric->value] = $this->getDashboardWeeklyMetricData($athlete, $calculatedMetric, $options['period'], $athleteMetrics);
                }
                $athleteData['weekly_metrics_data'] = $weeklyMetricsData;
            }

            if ($options['include_latest_daily_metrics']) {
                // Adapter getLatestMetricsGroupedByDate pour prendre une collection
                $athleteData['latest_daily_metrics'] = $this->getLatestMetricsGroupedByDateForCollection($athleteMetrics, $options['period']);
            }

            if (! empty($options['include_alerts'])) {
                $period = $options['period'] ?? 'last_60_days';
                $this->metricAlertsService->getAlerts($athlete, $athleteMetrics, $athletePlanWeeks, $period, $options);
            }

            if ($options['include_menstrual_cycle'] && $athlete->gender->value === 'w') {
                $athleteData['menstrual_cycle_info'] = $this->deduceMenstrualCyclePhase($athlete, $athleteMetrics);
            }

            if ($options['include_readiness_status']) {
                $athleteData['readiness_status'] = $this->metricReadinessService->getAthleteReadinessStatus($athlete, $athleteMetrics);
            }

            // Ajouter les données calculées à l'objet athlète
            foreach ($athleteData as $key => $value) {
                $athlete->{$key} = $value;
            }

            return $athleteData;
        });
    }

    protected function determineMaxStartDate(array $options): Carbon
    {
        $now = Carbon::now();
        $startDate = $this->getStartDateFromPeriod($options['period']);

        // Si les tendances sont incluses, s'assurer d'avoir au moins 60 jours de données
        if ($options['include_dashboard_metrics'] || $options['include_weekly_metrics'] || ! empty($options['include_alerts'])) {
            $startDate = $startDate->min($now->copy()->subDays(60)->startOfDay());
        }

        return $startDate;
    }

    /**
     * Get latest metrics grouped by date for a collection of metrics.
     */
    protected function getLatestMetricsGroupedByDateForCollection(Collection $athleteMetrics, string $period): Collection
    {
        $startDate = $this->getStartDateFromPeriod($period);
        $endDate = now()->endOfDay();

        // Étape 1: Filtrer les métriques pour éliminer les duplicata de type même heure/date
        $filteredMetrics = $athleteMetrics->filter(function (Metric $metric) use ($startDate, $endDate) {
            return $metric->date->between($startDate, $endDate);
        });

        // Étape 2: Trier par date les éléments de chaque groupe
        $sortedMetrics = $filteredMetrics->sortByDesc('date');

        // Étape 3: Grouper les métriques par jour
        $groupedData = $sortedMetrics->groupBy(function (Metric $metric) {
            return $metric->date->format('Y-m-d');
        });

        // Étape finale: Trier les entités pour avoir un ordre chronologique
        return $groupedData->sortByDesc('date');
    }

    /**
     * Prépare les données du tableau de bord pour une collection d'athlètes en optimisant les requêtes.
     */
    public function getBulkAthletesDashboardData(Collection $athletes, string $period): Collection
    {
        $athleteIds = $athletes->pluck('id');
        if ($athleteIds->isEmpty()) {
            return collect();
        }

        // Récupération de toutes les données nécessaires en une seule fois
        $startDate = $this->getStartDateFromPeriod($period);

        $allMetricsByAthlete = Metric::whereIn('athlete_id', $athleteIds)
            ->where('date', '>=', $startDate)
            ->orderBy('date', 'asc')
            ->get()
            ->groupBy('athlete_id');

        $athletesTrainingPlansIds = $athletes->pluck('current_training_plan.id')->filter()->values();

        $allTrainingPlanWeeksByAthleteTrainingPlanId = TrainingPlanWeek::whereIn('training_plan_id', $athletesTrainingPlansIds)
            ->where('start_date', '>=', $startDate->copy()->subWeek())
            ->get()
            ->groupBy('training_plan_id');

        // Itération sur les athlètes pour calculer les données spécifiques à chacun
        return $athletes->map(function ($athlete) use ($allMetricsByAthlete, $allTrainingPlanWeeksByAthleteTrainingPlanId, $period) {
            $athleteMetrics = $allMetricsByAthlete->get($athlete->id) ?? collect();
            $athleteTrainingPlansId = $athlete->currentTrainingPlan?->id;
            $athletePlanWeeks = $allTrainingPlanWeeksByAthleteTrainingPlanId->get($athleteTrainingPlansId) ?? collect();

            $metricsDataForDashboard = [];

            $dashboardMetricTypes = [
                MetricType::MORNING_HRV,
                MetricType::POST_SESSION_SESSION_LOAD,
                MetricType::POST_SESSION_SUBJECTIVE_FATIGUE,
                MetricType::MORNING_GENERAL_FATIGUE,
                MetricType::MORNING_SLEEP_QUALITY,
                MetricType::MORNING_BODY_WEIGHT_KG,
            ];

            foreach ($dashboardMetricTypes as $metricType) {
                $metricsForType = $athleteMetrics->where('metric_type', $metricType->value);
                $metricsDataForDashboard[$metricType->value] = $this->getDashboardMetricDataForCollection($metricsForType, $metricType, $period);
            }

            $metricsDataForDashboard[CalculatedMetric::CIH->value] = $this->getDashboardWeeklyMetricDataForCollection($athlete, $athleteMetrics, CalculatedMetric::CIH, $period, $athletePlanWeeks);
            $metricsDataForDashboard[CalculatedMetric::SBM->value] = $this->getDashboardWeeklyMetricDataForCollection($athlete, $athleteMetrics, CalculatedMetric::SBM, $period, $athletePlanWeeks);
            $metricsDataForDashboard[CalculatedMetric::CPH->value] = $this->getDashboardWeeklyMetricDataForCollection($athlete, $athleteMetrics, CalculatedMetric::CPH, 'period', $athletePlanWeeks);
            $metricsDataForDashboard[CalculatedMetric::CIH_NORMALIZED->value] = $this->getDashboardWeeklyMetricDataForCollection($athlete, $athleteMetrics, CalculatedMetric::CIH_NORMALIZED, $period, $athletePlanWeeks);
            $metricsDataForDashboard[CalculatedMetric::RATIO_CIH_CPH->value] = $this->getDashboardWeeklyMetricDataForCollection($athlete, $athleteMetrics, CalculatedMetric::RATIO_CIH_CPH, $period, $athletePlanWeeks);
            $metricsDataForDashboard[CalculatedMetric::RATIO_CIH_NORMALIZED_CPH->value] = $this->getDashboardWeeklyMetricDataForCollection($athlete, $athleteMetrics, CalculatedMetric::RATIO_CIH_NORMALIZED_CPH, $period, $athletePlanWeeks);

            $athlete->metricsDataForDashboard = $metricsDataForDashboard;

            // $athlete->alerts = $this->getAthleteAlertsForCollection($athlete, $athleteMetrics, $period);
            // $athlete->menstrualCycleInfo = $this->deduceMenstrualCyclePhaseForCollection($athlete, $athleteMetrics);
            // $athlete->chargeAlerts = $this->getChargeAlertsForCollection($athlete, $athleteMetrics, $athletePlanWeeks, now()->startOfWeek(Carbon::MONDAY));
            // $athlete->readinessStatus = $this->getAthleteReadinessStatus($athlete, $athleteMetrics);

            return $athlete;
        });
    }

    /**
     * Prépare les données d'une métrique pour le tableau de bord à partir d'une collection pré-filtrée.
     */
    protected function getDashboardMetricDataForCollection(Collection $metricsForPeriod, MetricType $metricType, string $period): array
    {
        $valueColumn = $metricType->getValueColumn();
        $metricData = [
            'label'                     => $metricType->getLabel(),
            'short_label'               => $metricType->getLabelShort(),
            'description'               => $metricType->getDescription(),
            'unit'                      => $metricType->getUnit(),
            'last_value'                => null,
            'formatted_last_value'      => 'N/A',
            'average_7_days'            => null,
            'formatted_average_7_days'  => 'N/A',
            'average_30_days'           => null,
            'formatted_average_30_days' => 'N/A',
            'trend_icon'                => 'ellipsis-horizontal',
            'trend_color'               => 'zinc',
            'trend_percentage'          => 'N/A',
            'chart_data'                => [],
            'is_numerical'              => ($valueColumn !== 'note'),
        ];

        $metricData['chart_data'] = $this->prepareChartDataForSingleMetric($metricsForPeriod, $metricType);

        $lastMetric = $metricsForPeriod->sortByDesc('date')->first();
        if ($lastMetric) {
            $metricValue = $lastMetric->{$valueColumn};
            $metricData['last_value'] = $metricValue;
            $metricData['formatted_last_value'] = $this->formatMetricValue($metricValue, $metricType);
        }

        if ($metricData['is_numerical']) {
            $trends = $this->metricTrendsService->getMetricTrendsForCollection($metricsForPeriod, $metricType);
            $metricData['average_7_days'] = $trends['averages']['Derniers 7 jours'] ?? null;
            $metricData['average_30_days'] = $trends['averages']['Derniers 30 jours'] ?? null;
            $metricData['formatted_average_7_days'] = $this->formatMetricValue($metricData['average_7_days'], $metricType);
            $metricData['formatted_average_30_days'] = $this->formatMetricValue($metricData['average_30_days'], $metricType);

            $evolutionTrendData = $this->metricTrendsService->getEvolutionTrendForCollection($metricsForPeriod, $metricType);
            if ($metricData['average_7_days'] !== null && $evolutionTrendData['trend'] !== 'N/A') {
                $optimalDirection = $metricType->getTrendOptimalDirection();

                $metricData['trend_icon'] = match ($evolutionTrendData['trend']) {
                    'increasing' => 'arrow-trending-up',
                    'decreasing' => 'arrow-trending-down',
                    default      => 'minus', // stable
                };

                $metricData['trend_color'] = match ($evolutionTrendData['trend']) {
                    'increasing' => ($optimalDirection === 'good' ? 'lime' : ($optimalDirection === 'bad' ? 'rose' : 'zinc')),
                    'decreasing' => ($optimalDirection === 'good' ? 'rose' : ($optimalDirection === 'bad' ? 'lime' : 'zinc')),
                    default      => 'zinc', // stable
                };
            }

            if ($metricData['average_7_days'] !== null && $metricData['average_30_days'] !== null && $metricData['average_30_days'] != 0) {
                $change = (($metricData['average_7_days'] - $metricData['average_30_days']) / $metricData['average_30_days']) * 100;
                $metricData['trend_percentage'] = ($change > 0 ? '+' : '').number_format($change, 1).'%';
            }
        }

        return $metricData;
    }

    /**
     * Prépare les données hebdomadaires d'une métrique pour le tableau de bord à partir de collections pré-filtrées.
     */
    protected function getDashboardWeeklyMetricDataForCollection(Athlete $athlete, Collection $allAthleteMetrics, CalculatedMetric $metricKey, string $period, Collection $athletePlanWeeks): array
    {
        $now = Carbon::now();
        $startDate = $this->getStartDateFromPeriod($period)->startOfWeek(Carbon::MONDAY);
        $endDate = $now->copy()->endOfWeek(Carbon::SUNDAY);

        $allWeeklyData = new Collection;
        $weekPeriod = CarbonPeriod::create($startDate, '1 week', $endDate);

        // Calcule les valeurs hebdomadaires pour toute la période
        foreach ($weekPeriod as $weekStartDate) {
            $weekEndDate = $weekStartDate->copy()->endOfWeek(Carbon::SUNDAY);
            $metricsForWeek = $allAthleteMetrics->whereBetween('date', [$weekStartDate, $weekEndDate]);

            $cih = $this->metricCalculationService->calculateCihForCollection($metricsForWeek);

            $sbmSum = 0;
            $sbmCount = 0;
            $dayPeriod = CarbonPeriod::create($weekStartDate, '1 day', $weekEndDate);
            $sbmMetricsForWeek = $metricsForWeek->whereIn('metric_type', [
                MetricType::MORNING_SLEEP_QUALITY,
                MetricType::MORNING_GENERAL_FATIGUE,
                MetricType::MORNING_PAIN,
                MetricType::MORNING_MOOD_WELLBEING,
            ])->groupBy(fn ($m) => $m->date->format('Y-m-d'));

            foreach ($dayPeriod as $date) {
                $dateStr = $date->format('Y-m-d');
                if ($sbmMetricsForWeek->has($dateStr)) {
                    $sbmValue = $this->metricCalculationService->calculateSbmForCollection($sbmMetricsForWeek->get($dateStr));
                    if ($sbmValue > 0) {
                        $sbmSum += $sbmValue;
                        $sbmCount++;
                    }
                }
            }
            $sbm = $sbmCount > 0 ? $sbmSum / $sbmCount : null;

            $planWeek = $athletePlanWeeks->firstWhere('start_date', $weekStartDate->toDateString());
            $cph = $planWeek ? $this->metricCalculationService->calculateCph($planWeek) : 0.0;

            $cihNormalized = $this->metricCalculationService->calculateCihNormalizedForCollection($metricsForWeek);
            $ratioCihCph = ($cih > 0 && $cph > 0) ? $this->metricCalculationService->calculateRatio($cih, $cph) : null;
            $ratioCihNormalizedCph = ($cihNormalized > 0 && $cph > 0) ? $this->metricCalculationService->calculateRatio($cihNormalized, $cph) : null;

            $value = match ($metricKey) {
                CalculatedMetric::CIH                      => $cih,
                CalculatedMetric::SBM                      => $sbm,
                CalculatedMetric::CPH                      => $cph,
                CalculatedMetric::CIH_NORMALIZED           => $cihNormalized,
                CalculatedMetric::RATIO_CIH_CPH            => $ratioCihCph,
                CalculatedMetric::RATIO_CIH_NORMALIZED_CPH => $ratioCihNormalizedCph,
                default                                    => null,
            };

            if (is_numeric($value)) {
                $allWeeklyData->push((object) ['date' => $weekStartDate->copy(), 'value' => $value]);
            }
        }

        // Calcule les statistiques pour le tableau de bord à partir des données hebdomadaires
        $lastValue = $allWeeklyData->last()->value ?? null;

        $dataFor7Days = $allWeeklyData->where('date', '>=', $now->copy()->subDays(7)->startOfDay());
        $average7Days = $dataFor7Days->isNotEmpty() ? $dataFor7Days->avg('value') : null;

        $dataFor30Days = $allWeeklyData->where('date', '>=', $now->copy()->subDays(30)->startOfDay());
        $average30Days = $dataFor30Days->isNotEmpty() ? $dataFor30Days->avg('value') : null;

        $trend = $this->metricTrendsService->calculateTrendFromNumericCollection($allWeeklyData);
        $changePercentage = 'N/A';
        if (is_numeric($average7Days) && is_numeric($average30Days) && $average30Days != 0) {
            $change = (($average7Days - $average30Days) / $average30Days) * 100;
            $changePercentage = ($change > 0 ? '+' : '').number_format($change, 1).'%';
        }

        // Prépare les données pour le retour
        return [
            'label'                     => $metricKey->getLabelShort(),
            'formatted_last_value'      => is_numeric($lastValue) ? number_format($lastValue, 1) : 'N/A',
            'formatted_average_7_days'  => is_numeric($average7Days) ? number_format($average7Days, 1) : 'N/A',
            'formatted_average_30_days' => is_numeric($average30Days) ? number_format($average30Days, 1) : 'N/A',
            'is_numerical'              => true,
            'trend_icon'                => $trend['trend'] !== 'N/A' ? match ($trend['trend']) {
                'increasing' => 'arrow-trending-up', 'decreasing' => 'arrow-trending-down', default => 'minus'
            } : 'ellipsis-horizontal',
            'trend_color' => $trend['trend'] !== 'N/A' ? match ($trend['trend']) {
                'increasing' => ($metricKey->getTrendOptimalDirection() === 'good' ? 'lime' : ($metricKey->getTrendOptimalDirection() === 'bad' ? 'rose' : 'zinc')),
                'decreasing' => ($metricKey->getTrendOptimalDirection() === 'good' ? 'rose' : ($metricKey->getTrendOptimalDirection() === 'bad' ? 'lime' : 'zinc')),
                default      => 'zinc',
            } : 'zinc',
            'trend_percentage' => $changePercentage,
            'chart_data'       => [
                'labels' => $allWeeklyData->pluck('date')->map(fn ($d) => $d->format('W Y'))->all(),
                'data'   => $allWeeklyData->pluck('value')->all(),
            ],
        ];
    }

    /**
     * Obtient la date de début d'une période donnée.
     */
    public function getStartDateFromPeriod(string $period): Carbon
    {
        $now = Carbon::now();

        return match ($period) {
            'last_7_days'   => $now->copy()->subDays(7)->startOfDay(),
            'last_14_days'  => $now->copy()->subDays(14)->startOfDay(),
            'last_30_days'  => $now->copy()->subDays(30)->startOfDay(),
            'last_60_days'  => $now->copy()->subDays(60)->startOfDay(),
            'last_90_days'  => $now->copy()->subDays(90)->startOfDay(),
            'last_6_months' => $now->copy()->subMonths(6)->startOfDay(),
            'last_year'     => $now->copy()->subYear()->startOfDay(),
            'all_time'      => Carbon::createFromTimestamp(0),
            default         => $now->copy()->subDays(60)->startOfDay(),
        };
    }

    /**
     * Récupère les données de métriques pour un athlète donné, avec des filtres.
     *
     * @param  array  $filters  (metric_type, period)
     * @return Collection<Metric>
     */
    public function getAthleteMetrics(Athlete $athlete, array $filters = []): Collection
    {
        $query = $athlete->metrics()->orderBy('date');

        if (isset($filters['metric_type']) && $filters['metric_type'] !== 'all') {
            if (MetricType::tryFrom($filters['metric_type'])) {
                $query->where('metric_type', $filters['metric_type']);
            }
        }

        if (isset($filters['period'])) {
            $this->applyPeriodFilter($query, $filters['period']);
        }

        return $query->get();
    }

    /**
     * Applique le filtre de période à la requête.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $period  (e.g., 'last_7_days', 'last_30_days', 'last_6_months', 'last_year', 'all_time', 'custom:start_date,end_date')
     */
    protected function applyPeriodFilter($query, string $period): void
    {
        $now = Carbon::now();

        switch ($period) {
            case 'last_7_days':
                $query->where('date', '>=', $now->copy()->subDays(7)->startOfDay());
                break;
            case 'last_14_days':
                $query->where('date', '>=', $now->copy()->subDays(14)->startOfDay());
                break;
            case 'last_30_days':
                $query->where('date', '>=', $now->copy()->subDays(30)->startOfDay());
                break;
            case 'last_60_days':
                $query->where('date', '>=', $now->copy()->subDays(60)->startOfDay());
                break;
            case 'last_90_days':
                $query->where('date', '>=', $now->copy()->subDays(90)->startOfDay());
                break;
            case 'last_6_months':
                $query->where('date', '>=', $now->copy()->subMonths(6)->startOfDay());
                break;
            case 'last_year':
                $query->where('date', '>=', $now->copy()->subYear()->startOfDay());
                break;
            case 'all_time':
                break;
            default:
                if (str_starts_with($period, 'custom:')) {
                    $dates = explode(',', substr($period, 7));
                    if (count($dates) === 2) {
                        $startDate = Carbon::parse($dates[0])->startOfDay();
                        $endDate = Carbon::parse($dates[1])->endOfDay();
                        $query->whereBetween('date', [$startDate, $endDate]);
                    }
                }
                break;
        }
    }

    /**
     * Prépare les données d'une seule métrique pour l'affichage sur un graphique.
     *
     * @param  Collection<Metric>  $metrics
     * @param  MetricType  $metricType  L'énumération MetricType pour obtenir les détails du champ de valeur.
     * @return array ['labels' => [], 'data' => [], 'unit' => string|null, 'label' => string]
     */
    public function prepareChartDataForSingleMetric(Collection $metrics, MetricType $metricType): array
    {
        $labels = [];
        $data = [];
        $labelsAndData = [];
        $valueColumn = $metricType->getValueColumn();
        $unit = $metricType->getUnit();

        $sortedMetrics = $metrics->sortBy('date');

        foreach ($sortedMetrics as $metric) {
            if ($metric->metric_type === $metricType) {
                $dateLabel = $metric->date->format('Y-m-d');
                $labels[] = $dateLabel;
                $value = $metric->{$valueColumn};
                $numericValue = is_numeric($value) ? (float) $value : null;
                $data[] = $numericValue;
                $labelsAndData[] = [
                    'label' => $dateLabel,
                    'value' => $numericValue,
                    'unit'  => $unit,
                ];
            }
        }

        return [
            'labels'          => $labels,
            'data'            => $data,
            'labels_and_data' => $labelsAndData,
            'unit'            => $unit,
            'label'           => $metricType->getLabel(),
        ];
    }

    /**
     * Prépare les données de plusieurs métriques pour l'affichage sur un graphique.
     *
     * @param  Collection<Metric>  $metrics  La collection complète de métriques (peut contenir plusieurs types).
     * @param  array<MetricType>  $metricTypes  Les énumérations MetricType à inclure dans le graphique.
     * @return array ['labels' => [], 'datasets' => []]
     */
    public function prepareChartDataForMultipleMetrics(Collection $metrics, array $metricTypes): array
    {
        $allLabels = $metrics->pluck('date')->unique()->map(fn ($date) => $date->format('Y-m-d'))->sort()->values()->toArray();
        $datasets = [];
        $labelsAndData = [];

        foreach ($metricTypes as $metricType) {
            $valueColumn = $metricType->getValueColumn();
            $label = $metricType->getLabel();
            $unit = $metricType->getUnit();

            $groupedMetrics = $metrics->filter(fn ($m) => $m->metric_type === $metricType)
                ->groupBy(fn ($m) => $m->date->format('Y-m-d'));

            $data = [];
            foreach ($allLabels as $dateLabel) {
                $value = $groupedMetrics->has($dateLabel) ? $groupedMetrics[$dateLabel]->first()->{$valueColumn} : null;
                $data[] = is_numeric($value) ? (float) $value : null;

                $labelsAndData[$metricType->value][] = [
                    'label' => $dateLabel,
                    'value' => is_numeric($value) ? (float) $value : null,
                    'unit'  => $unit,
                ];
            }

            $datasets[] = [
                'label' => $label.($unit ? ' ('.$unit.')' : ''),
                'data'  => $data,
            ];
        }

        return [
            'labels'          => $allLabels,
            'datasets'        => $datasets,
            'labels_and_data' => $labelsAndData,
        ];
    }

    /**
     * Récupère les métriques les plus récentes groupées par date.
     *
     * Récupère les métriques les plus récentes groupées par date pour un athlète.
     *
     * @param  int  $limit  Le nombre maximum de métriques brutes à récupérer.
     * @return Collection<string, Collection<Metric>> Une collection de métriques groupées par date (format Y-m-d), triées de la plus récente à la plus ancienne.
     */
    public function getLatestMetricsGroupedByDate(Athlete $athlete, int $limit = 50): Collection
    {
        $metrics = $athlete->metrics()
            ->whereNotNull('date')
            ->orderByDesc('date')
            ->limit($limit * count(MetricType::cases()))
            ->get();

        return $metrics->groupBy(fn ($metric) => $metric->date->format('Y-m-d'))
            ->sortByDesc(fn ($metrics, $date) => $date);
    }

    /**
     * Prépare toutes les données agrégées pour le tableau de bord d'une métrique spécifique.
     */
    public function getDashboardMetricData(Athlete $athlete, MetricType $metricType, string $period): array
    {
        $metricsForPeriod = $this->getAthleteMetrics($athlete, ['metric_type' => $metricType->value, 'period' => $period]);

        $valueColumn = $metricType->getValueColumn();

        $metricData = [
            'label'                     => $metricType->getLabel(),
            'short_label'               => $metricType->getLabelShort(),
            'description'               => $metricType->getDescription(),
            'unit'                      => $metricType->getUnit(),
            'last_value'                => null,
            'formatted_last_value'      => 'N/A',
            'average_7_days'            => null,
            'formatted_average_7_days'  => 'N/A',
            'average_30_days'           => null,
            'formatted_average_30_days' => 'N/A',
            'trend_icon'                => 'ellipsis-horizontal',
            'trend_color'               => 'zinc',
            'trend_percentage'          => 'N/A',
            'chart_data'                => [],
            'is_numerical'              => ($valueColumn !== 'note'),
        ];

        $metricData['chart_data'] = $this->prepareChartDataForSingleMetric($metricsForPeriod, $metricType);

        $lastMetric = $metricsForPeriod->sortByDesc('date')->first();
        if ($lastMetric) {
            $metricValue = $lastMetric->{$valueColumn};
            $metricData['last_value'] = $metricValue;
            $metricData['formatted_last_value'] = $this->formatMetricValue($metricValue, $metricType);
        }

        if ($metricData['is_numerical']) {
            $trends = $this->metricTrendsService->getMetricTrendsForCollection($metricsForPeriod, $metricType);

            $metricData['average_7_days'] = $trends['averages']['Derniers 7 jours'] ?? null;
            $metricData['average_30_days'] = $trends['averages']['Derniers 30 jours'] ?? null;

            $metricData['formatted_average_7_days'] = $this->formatMetricValue($metricData['average_7_days'], $metricType);
            $metricData['formatted_average_30_days'] = $this->formatMetricValue($metricData['average_30_days'], $metricType);

            $evolutionTrendData = $this->metricTrendsService->getEvolutionTrendForCollection($metricsForPeriod, $metricType);

            if ($metricData['average_7_days'] !== null && $evolutionTrendData && isset($evolutionTrendData['trend'])) {
                switch ($evolutionTrendData['trend']) {
                    case 'increasing':
                        $metricData['trend_icon'] = 'arrow-trending-up';
                        $metricData['trend_color'] = 'lime';
                        break;
                    case 'decreasing':
                        $metricData['trend_icon'] = 'arrow-trending-down';
                        $metricData['trend_color'] = 'rose';
                        break;
                    case 'stable':
                        $metricData['trend_icon'] = 'minus';
                        $metricData['trend_color'] = 'zinc';
                        break;
                    default:
                        // default values already set
                        break;
                }
            }

            if ($metricData['average_7_days'] !== null && $metricData['average_30_days'] !== null && $metricData['average_30_days'] !== 0) {
                $change = (($metricData['average_7_days'] - $metricData['average_30_days']) / $metricData['average_30_days']) * 100;
                $metricData['trend_percentage'] = ($change > 0 ? '+' : '').number_format($change, 1).'%';
            } elseif ($metricData['average_7_days'] !== null && $metricType->getValueColumn() !== 'note') {
                $metricData['trend_percentage'] = $this->formatMetricValue($metricData['average_7_days'], $metricType);
            }
        }

        return $metricData;
    }

    /**
     * Formate une valeur de métrique en fonction de son type et de sa précision.
     */
    public function formatMetricValue(mixed $value, MetricType $metricType): string
    {
        if ($value === null) {
            return 'N/A';
        }
        if ($metricType->getValueColumn() === 'note') {
            return (string) $value;
        }

        $formattedValue = number_format($value, $metricType->getPrecision());
        $unit = $metricType->getUnit();
        $scale = $metricType->getScale();

        return $formattedValue.($unit ? ' '.$unit : '').($scale ? '/'.$scale : '');
    }

    /**
     * Récupère un résumé des métriques hebdomadaires (CIH, SBM, CPH, Ratio CIH/CPH) pour un athlète.
     *
     * @return array ['cih' => float, 'sbm' => float|string, 'cph' => float, 'ratio_cih_cph' => float|string]
     */
    public function getAthleteWeeklyMetricsSummary(Athlete $athlete, Carbon $weekStartDate, ?Collection $allMetrics = null): array
    {
        $weekEndDate = $weekStartDate->copy()->endOfWeek(Carbon::SUNDAY);
        $metricsForWeek = $allMetrics ? $allMetrics->whereBetween('date', [$weekStartDate, $weekEndDate]) : $athlete->metrics()->whereBetween('date', [$weekStartDate, $weekEndDate])->get();

        $cih = $this->metricCalculationService->calculateCihForCollection($metricsForWeek);

        $sbmSum = 0;
        $sbmCount = 0;
        $periodCarbon = \Carbon\CarbonPeriod::create($weekStartDate, '1 day', $weekEndDate);
        foreach ($periodCarbon as $date) {
            $dailyMetrics = $metricsForWeek->filter(fn ($m) => $m->date->format('Y-m-d') === $date->format('Y-m-d'));
            $sbmValue = $this->metricCalculationService->calculateSbmForCollection($dailyMetrics);
            if ($sbmValue !== null) {
                $sbmSum += $sbmValue;
                $sbmCount++;
            }
        }
        $sbm = $sbmCount > 0 ? round($sbmSum / $sbmCount, 1) : 'N/A';

        $trainingPlanWeek = $this->getTrainingPlanWeekForAthlete($athlete, $weekStartDate);
        $cph = $trainingPlanWeek ? $this->metricCalculationService->calculateCph($trainingPlanWeek) : 0.0;

        $ratioCihCph = ($cih > 0 && $cph > 0) ? round($this->metricCalculationService->calculateRatio($cih, $cph), 2) : 'N/A';

        $cihNormalized = $this->metricCalculationService->calculateCihNormalizedForCollection($metricsForWeek);
        $ratioCihNormalizedCph = ($cihNormalized > 0 && $cph > 0) ? round($this->metricCalculationService->calculateRatio($cihNormalized, $cph), 2) : 'N/A';

        return [
            CalculatedMetric::CIH->value                      => $cih,
            CalculatedMetric::SBM->value                      => $sbm,
            CalculatedMetric::CPH->value                      => $cph,
            CalculatedMetric::CIH_NORMALIZED->value           => $cihNormalized,
            CalculatedMetric::RATIO_CIH_CPH->value            => $ratioCihCph,
            CalculatedMetric::RATIO_CIH_NORMALIZED_CPH->value => $ratioCihNormalizedCph,
        ];
    }

    /**
     * Prépare les données de graphique pour les métriques hebdomadaires (CIH, SBM, CPH, Ratio CIH/CPH) sur une période donnée.
     *
     * @param  Athlete  $athlete  L'athlète concerné.
     * @param  string  $period  La période pour laquelle récupérer les données (ex: 'last_60_days').
     * @param  CalculatedMetric  $metricKey  La clé de la métrique hebdomadaire (ex: 'cih', 'sbm').
     * @return array Les données formatées pour un graphique.
     */
    public function getWeeklyMetricsChartData(Athlete $athlete, string $period, CalculatedMetric $metricKey, ?Collection $allMetrics = null): array
    {
        $labels = [];
        $data = [];
        $labelsAndData = [];

        $now = Carbon::now();
        $startDate = match ($period) {
            'last_7_days'   => $now->copy()->subDays(7)->startOfDay(),
            'last_14_days'  => $now->copy()->subDays(14)->startOfDay(),
            'last_30_days'  => $now->copy()->subDays(30)->startOfDay(),
            'last_60_days'  => $now->copy()->subDays(60)->startOfDay(),
            'last_90_days'  => $now->copy()->subDays(90)->startOfDay(),
            'last_6_months' => $now->copy()->subMonths(6)->startOfDay(),
            'last_year'     => $now->copy()->subYear()->startOfDay(),
            default         => $now->copy()->subDays(60)->startOfDay(),
        };

        $currentWeek = $startDate->startOfWeek(Carbon::MONDAY);
        $endWeek = $now->endOfWeek(Carbon::SUNDAY);

        while ($currentWeek->lessThanOrEqualTo($endWeek)) {
            $summary = $this->getAthleteWeeklyMetricsSummary($athlete, $currentWeek, $allMetrics);
            $weekLabel = $currentWeek->format('W Y');
            $value = $summary[$metricKey->value];

            $labels[] = $weekLabel;
            $numericValue = is_numeric($value) ? (float) $value : null;
            $data[] = $numericValue;
            $labelsAndData[] = [
                'label' => $weekLabel,
                'value' => $numericValue,
                'unit'  => null,
            ];

            $currentWeek->addWeek();
        }

        return [
            'labels'          => $labels,
            'data'            => $data,
            'labels_and_data' => $labelsAndData,
            'unit'            => null,
            'label'           => $metricKey->getLabelShort(),
        ];
    }

    /**
     * Prépare toutes les données agrégées pour le tableau de bord d'une métrique hebdomadaire spécifique.
     *
     * @param  Athlete  $athlete  L'athlète concerné.
     * @param  CalculatedMetric  $metricKey  La clé de la métrique hebdomadaire (ex: 'cih', 'sbm').
     * @param  string  $period  La période pour laquelle récupérer les données.
     * @return array Les données formatées pour le tableau de bord.
     */
    public function getDashboardWeeklyMetricData(Athlete $athlete, CalculatedMetric $metricKey, string $period, ?Collection $allMetrics = null): array
    {
        $now = Carbon::now();
        $currentWeekStartDate = $now->startOfWeek(Carbon::MONDAY);

        $metricData = [
            'label'                     => $metricKey->getLabel(),
            'short_label'               => $metricKey->getLabelShort(),
            'description'               => $metricKey->getDescription(),
            'unit'                      => null,
            'last_value'                => null,
            'formatted_last_value'      => 'N/A',
            'average_7_days'            => null,
            'formatted_average_7_days'  => 'N/A',
            'average_30_days'           => null,
            'formatted_average_30_days' => 'N/A',
            'trend_icon'                => 'ellipsis-horizontal',
            'trend_color'               => 'zinc',
            'trend_percentage'          => 'N/A',
            'chart_data'                => [],
            'is_numerical'              => true,
        ];

        $weeklySummary = $this->getAthleteWeeklyMetricsSummary($athlete, $currentWeekStartDate, $allMetrics);
        $currentValue = $weeklySummary[$metricKey->value];
        $metricData['last_value'] = $currentValue;
        $metricData['formatted_last_value'] = is_numeric($currentValue) ? number_format($currentValue, 1) : 'N/A';

        $metricData['chart_data'] = $this->getWeeklyMetricsChartData($athlete, $period, $metricKey, $allMetrics);

        $allWeeklyData = new Collection;
        $startDateForAverages = match ($period) {
            'last_7_days'   => $now->copy()->subDays(7)->startOfWeek(Carbon::MONDAY),
            'last_14_days'  => $now->copy()->subDays(14)->startOfWeek(Carbon::MONDAY),
            'last_30_days'  => $now->copy()->subDays(30)->startOfWeek(Carbon::MONDAY),
            'last_60_days'  => $now->copy()->subDays(60)->startOfWeek(Carbon::MONDAY),
            'last_90_days'  => $now->copy()->subDays(90)->startOfWeek(Carbon::MONDAY),
            'last_6_months' => $now->copy()->subMonths(6)->startOfWeek(Carbon::MONDAY),
            'last_year'     => $now->copy()->subYear()->startOfWeek(Carbon::MONDAY),
            default         => $now->copy()->subDays(60)->startOfWeek(Carbon::MONDAY),
        };

        $tempWeek = $startDateForAverages->copy();
        while ($tempWeek->lessThanOrEqualTo($now->endOfWeek(Carbon::SUNDAY))) {
            $summary = $this->getAthleteWeeklyMetricsSummary($athlete, $tempWeek, $allMetrics);
            if (is_numeric($summary[$metricKey->value])) {
                $allWeeklyData->push((object) ['date' => $tempWeek->copy(), 'value' => $summary[$metricKey->value]]);
            }
            $tempWeek->addWeek();
        }

        $average7Days = null;
        $average30Days = null;

        $relevantDataFor7Days = $allWeeklyData->filter(fn ($item) => $item->date->greaterThanOrEqualTo($now->copy()->subDays(7)->startOfWeek(Carbon::MONDAY)));
        if ($relevantDataFor7Days->count() > 0) {
            $average7Days = $relevantDataFor7Days->avg('value');
        }

        $relevantDataFor30Days = $allWeeklyData->filter(fn ($item) => $item->date->greaterThanOrEqualTo($now->copy()->subDays(30)->startOfWeek(Carbon::MONDAY)));
        if ($relevantDataFor30Days->count() > 0) {
            $average30Days = $relevantDataFor30Days->avg('value');
        }

        $metricData['average_7_days'] = $average7Days;
        $metricData['formatted_average_7_days'] = is_numeric($average7Days) ? number_format($average7Days, 1) : 'N/A';
        $metricData['average_30_days'] = $average30Days;
        $metricData['formatted_average_30_days'] = is_numeric($average30Days) ? number_format($average30Days, 1) : 'N/A';

        $evolutionTrendData = $this->metricTrendsService->calculateTrendFromNumericCollection($allWeeklyData);

        if ($metricData['average_7_days'] !== null && $evolutionTrendData && isset($evolutionTrendData['trend'])) {
            switch ($evolutionTrendData['trend']) {
                case 'increasing':
                    $metricData['trend_icon'] = 'arrow-trending-up';
                    $metricData['trend_color'] = 'lime';
                    break;
                case 'decreasing':
                    $metricData['trend_icon'] = 'arrow-trending-down';
                    $metricData['trend_color'] = 'rose';
                    break;
                case 'stable':
                    $metricData['trend_icon'] = 'minus';
                    $metricData['trend_color'] = 'zinc';
                    break;
                default:
                    break;
            }
        }

        if (is_numeric($metricData['average_7_days']) && is_numeric($metricData['average_30_days'])) {
            if ($metricData['average_30_days'] !== 0.0) {
                $change = (($metricData['average_7_days'] - $metricData['average_30_days']) / $metricData['average_30_days']) * 100;
                $metricData['trend_percentage'] = ($change > 0 ? '+' : '').number_format($change, 1).'%';
            } else {
                $metricData['trend_percentage'] = ($metricData['average_7_days'] == 0.0) ? '0%' : '+INF%';
            }
        } elseif (is_numeric($metricData['average_7_days'])) {
            $metricData['trend_percentage'] = number_format($metricData['average_7_days'], 1);
        } else {
            $metricData['trend_percentage'] = 'N/A';
        }

        return $metricData;
    }

    /**
     * Récupère la TrainingPlanWeek pour un athlète et une date de début de semaine donnés.
     */
    public function getTrainingPlanWeekForAthlete(Athlete $athlete, Carbon $weekStartDate): ?TrainingPlanWeek
    {
        $assignedPlan = $athlete->currentTrainingPlan;

        if (! $assignedPlan) {
            return null;
        }

        return TrainingPlanWeek::where('training_plan_id', $assignedPlan->id)
            ->where('start_date', $weekStartDate->toDateString())
            ->first();
    }

    /**
     * Analyse les tendances des métriques et identifie des signaux d'alerte pour un athlète.
     *
     * @param  Athlete  $athlete  L'athlète pour lequel analyser les alertes.
     * @param  string  $period  Période pour l'analyse (ex: 'last_30_days', 'last_6_months'). Par défaut à 'last_60_days'.
     * @param  Collection|null  $metrics  Collection de métriques pré-chargée pour éviter des requêtes supplémentaires.
     * @return array Des drapeaux et des messages d'alerte.
     */
    public function getAthleteAlerts(Athlete $athlete, string $period = 'last_60_days', ?Collection $metrics = null): array
    {
        $alerts = [];
        if (is_null($metrics)) {
            $metrics = $this->getAthleteMetrics($athlete, ['period' => $period]);
        }

        // ** Alertes Générales (Hommes et Femmes) **

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

        // ** Alertes Spécifiques aux Femmes (potentiels signes de RED-S) **
        if ($athlete->gender->value === 'w') {
            $menstrualThresholds = self::ALERT_THRESHOLDS['MENSTRUAL_CYCLE'];
            $cycleData = $this->deduceMenstrualCyclePhase($athlete);

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
                $currentDayFatigue = $metrics->firstWhere('metric_type', MetricType::MORNING_GENERAL_FATIGUE)?->value;
                $currentDayPerformanceFeel = $metrics->firstWhere('metric_type', MetricType::POST_SESSION_PERFORMANCE_FEEL)?->value;

                if ($currentDayFatigue !== null && $currentDayFatigue >= $menstrualThresholds['menstrual_fatigue_min']) {
                    $alerts[] = ['type' => 'info', 'message' => 'Fatigue élevée ('.$currentDayFatigue."/10) pendant la phase menstruelle. Adapter l'entraînement peut être bénéfique."];
                }
                if ($currentDayPerformanceFeel !== null && $currentDayPerformanceFeel <= $menstrualThresholds['menstrual_perf_feel_max']) {
                    $alerts[] = ['type' => 'info', 'message' => 'Performance ressentie faible ('.$currentDayPerformanceFeel."/10) pendant la phase menstruelle. Évaluer l'intensité de l'entraînement."];
                }
            }
        }

        // Gérer les cas où aucune alerte spécifique n'a été détectée.
        // On vérifie d'abord s'il y a suffisamment de données pour une analyse.
        if ($metrics->isEmpty() || $metrics->count() < 5) {
            $alerts[] = ['type' => 'info', 'message' => 'Pas encore suffisamment de données enregistrées pour une analyse complète sur la période : '.str_replace('_', ' ', $period).'.'];
        }

        return $alerts;
    }

    /**
     * Retourne la direction optimale de la tendance pour une métrique hebdomadaire.
     */
    protected function getWeeklyMetricOptimalDirection(CalculatedMetric $metricKey): string
    {
        return $metricKey->getTrendOptimalDirection();
    }

    /**
     * Déduit la phase du cycle menstruel d'un athlète féminin.
     *
     * @param  Athlete  $athlete  L'athlète féminin concerné.
     * @param  Collection|null  $allMetrics  Collection de toutes les métriques de l'athlète (optionnel, pour optimisation).
     * @return array Un tableau contenant la phase du cycle menstruel et des informations associées.
     */
    public function deduceMenstrualCyclePhase(Athlete $athlete, ?Collection $allMetrics = null): array
    {
        $menstrualThresholds = self::ALERT_THRESHOLDS['MENSTRUAL_CYCLE'];

        $phase = 'Inconnue';
        $reason = 'Données de cycle non disponibles.';
        $averageCycleLength = null;
        $daysSinceLastPeriod = null;
        $lastPeriodStart = null;

        // Récupérer toutes les métriques J1 (Premier Jour des Règles) dans l'ordre chronologique inverse des deux dernière années
        if (is_null($allMetrics)) {
            $j1Metrics = $athlete->metrics()
                ->where('metric_type', MetricType::MORNING_FIRST_DAY_PERIOD->value)
                ->where('value', 1)
                ->orderBy('date', 'desc')
                ->limit(26)
                ->get();
        } else {
            $j1Metrics = $allMetrics->where('metric_type', MetricType::MORNING_FIRST_DAY_PERIOD->value)
                ->where('value', 1)
                ->sortByDesc('date')
                ->values();
        }

        // Déterminer le dernier J1 et les jours depuis le dernier J1
        if ($j1Metrics->isNotEmpty()) {
            $lastPeriodStart = Carbon::parse($j1Metrics->first()->date);
            $daysSinceLastPeriod = Carbon::now()->diffInDays($lastPeriodStart, true);
        }

        // Calculer les longueurs des cycles précédents et la moyenne
        $cycleLengths = [];
        if ($j1Metrics->count() >= 2) {
            for ($i = 0; $i < $j1Metrics->count() - 1; $i++) {
                $start = Carbon::parse($j1Metrics[$i + 1]->date);
                $end = Carbon::parse($j1Metrics[$i]->date);
                $cycleLengths[] = $end->diffInDays($start, true);
            }
            $averageCycleLength = count($cycleLengths) > 0 ? array_sum($cycleLengths) / count($cycleLengths) : null;
        }

        // --- DÉDUCTION DE LA PHASE DU CYCLE MENSTRUEL PAR ORDRE DE PRIORITÉ ---
        // 1. Cas : Données insuffisantes pour établir un historique de cycle
        if ($j1Metrics->count() < 2 || $averageCycleLength === null) {
            $phase = 'Inconnue';
            $reason = 'Enregistrez au moins deux J1 pour calculer la durée moyenne de votre cycle.';
        }
        // 2. Cas : Aménorrhée (absence prolongée de règles) - La condition la plus grave
        elseif ($daysSinceLastPeriod !== null && $averageCycleLength !== null && $daysSinceLastPeriod > ($averageCycleLength + $menstrualThresholds['amenorrhea_days_beyond_avg'])) {
            $phase = 'Aménorrhée';
            $reason = 'Absence prolongée de règles (plus de '.$menstrualThresholds['amenorrhea_days_beyond_avg'].' jours au-delà de la longueur moyenne du cycle attendue).';
        }
        // 3. Cas : Oligoménorrhée (longueur moyenne du cycle anormalement courte ou longue)
        elseif ($averageCycleLength !== null && ($averageCycleLength < $menstrualThresholds['oligomenorrhea_min_cycle'] || $averageCycleLength > $menstrualThresholds['oligomenorrhea_max_cycle'])) {
            $phase = 'Oligoménorrhée';
            $reason = 'Longueur moyenne du cycle hors de la plage normale ('.$menstrualThresholds['oligomenorrhea_min_cycle'].'-'.$menstrualThresholds['oligomenorrhea_max_cycle'].' jours).';
        }
        // 4. Cas : Potentiel retard ou cycle long (le cycle actuel dépasse la moyenne mais n'est pas Aménorrhée)
        elseif ($daysSinceLastPeriod !== null && $averageCycleLength !== null && $daysSinceLastPeriod >= $averageCycleLength + $menstrualThresholds['delayed_cycle_days_beyond_avg']) {
            $phase = 'Potentiel retard ou cycle long';
            $reason = 'Le nombre de jours sans règles est significativement plus long que la durée moyenne du cycle.';
        }
        // 5. Cas : Déduction des phases normales du cycle (si aucune anomalie majeure n'est détectée)
        elseif ($daysSinceLastPeriod !== null && $averageCycleLength !== null) {
            // Phase Menstruelle (généralement J1 à J5)
            if ($daysSinceLastPeriod >= 1 && $daysSinceLastPeriod <= 5) {
                $phase = 'Menstruelle';
                $reason = 'Phase de saignement.';
            }
            // Phase Folliculaire (post-menstruation, pré-ovulation)
            elseif ($daysSinceLastPeriod > 5 && $daysSinceLastPeriod < ($averageCycleLength / 2)) {
                $phase = 'Folliculaire';
                $reason = 'Développement des follicules ovariens.';
            }
            // Phase Ovulatoire (autour de l'ovulation estimée)
            elseif ($daysSinceLastPeriod >= ($averageCycleLength / 2) && $daysSinceLastPeriod <= ($averageCycleLength / 2) + 2) {
                $phase = 'Ovulatoire (estimée)';
                $reason = 'Libération de l\'ovule.';
            }
            // Phase Lutéale (post-ovulation, pré-prochaines règles)
            elseif ($daysSinceLastPeriod > ($averageCycleLength / 2) + 2 && $daysSinceLastPeriod < $averageCycleLength) {
                $phase = 'Lutéale';
                $reason = 'Préparation de l\'utérus pour une éventuelle grossesse.';
            }
            // Cas de secours pour les phases normales si non couvertes
            else {
                $phase = 'Inconnue';
                $reason = 'Impossible de déterminer la phase du cycle normal avec les données actuelles.';
            }
        }

        return [
            'phase'             => $phase,
            'reason'            => $reason,
            'days_in_phase'     => $daysSinceLastPeriod,
            'cycle_length_avg'  => $averageCycleLength !== null ? round($averageCycleLength) : null,
            'last_period_start' => $lastPeriodStart ? $lastPeriodStart->format('d.m.Y') : null,
        ];
    }
}
