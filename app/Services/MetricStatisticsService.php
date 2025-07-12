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

class MetricStatisticsService
{
    protected MetricCalculationService $metricCalculationService;

    protected MetricAlertsService $metricAlertsService;

    protected MetricTrendsService $metricTrendsService;

    protected MetricReadinessService $metricReadinessService;

    protected MetricMenstrualService $metricMenstrualService;

    public function __construct(MetricCalculationService $metricCalculationService, MetricAlertsService $metricAlertsService, MetricTrendsService $metricTrendsService, MetricReadinessService $metricReadinessService, MetricMenstrualService $metricMenstrualService)
    {
        $this->metricCalculationService = $metricCalculationService;
        $this->metricAlertsService = $metricAlertsService;
        $this->metricTrendsService = $metricTrendsService;
        $this->metricReadinessService = $metricReadinessService;
        $this->metricMenstrualService = $metricMenstrualService;
    }

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
            'include_alerts'               => ['general', 'charge', 'readiness', 'menstrual'],
            'include_menstrual_cycle'      => false,
            'include_readiness_status'     => false,
            'chart_metric_type'            => null,
            'chart_period'                 => null,
        ];
        $options = array_merge($defaultOptions, $options);

        // Assurer que chart_period est défini si chart_metric_type l'est
        if ($options['chart_metric_type'] && ! $options['chart_period']) {
            $options['chart_period'] = $options['period'];
        }

        $athleteIds = $athletes->pluck('id');
        if ($athleteIds->isEmpty()) {
            return collect();
        }

        // Déterminer les types de métriques brutes à récupérer, en incluant les métriques essentielles pour le statut de readiness si nécessaire.
        $metricTypes = $options['metric_types'];

        if ($options['include_readiness_status']) {
            $existingMetricValues = array_map(fn ($metricType) => $metricType->value, $metricTypes);
            $essentialMetricValues = array_map(fn ($metricType) => $metricType->value, $this->metricReadinessService::ESSENTIAL_DAILY_READINESS_METRICS);

            $combinedMetricValues = array_unique(array_merge($existingMetricValues, $essentialMetricValues));
            $metricTypes = array_map(fn ($value) => MetricType::from($value), $combinedMetricValues);
        }

        // Déterminer la date de début maximale pour la collecte des métriques brutes, en fonction de la période et des options d'inclusion.
        $maxStartDate = $this->determineMetricCollectionStartDate($options);

        // Pré-charger toutes les métriques brutes et les semaines de plan d'entraînement pour tous les athlètes.
        // Les métriques menstruelles (J1) sont incluses si l'option est activée, avec une période de 2 ans.
        $allMetricsQuery = Metric::whereIn('athlete_id', $athleteIds)
            ->where('date', '>=', $maxStartDate)
            ->whereIn('metric_type', array_map(fn ($metricType) => $metricType->value, $metricTypes))
            ->orderBy('date', 'asc');

        if ($options['include_menstrual_cycle']) {
            $allMetricsQuery->orWhere(function ($query) use ($athleteIds) {
                $query->whereIn('athlete_id', $athleteIds)
                    ->where('date', '>=', now()->copy()->subYears(2)->startOfDay())
                    ->where('metric_type', MetricType::MORNING_FIRST_DAY_PERIOD);
            });
        }

        $allMetricsByAthlete = $allMetricsQuery->get()->groupBy('athlete_id');

        $athletesTrainingPlansIds = $athletes->pluck('current_training_plan.id')->filter()->values();
        $allTrainingPlanWeeksByAthleteTrainingPlanId = TrainingPlanWeek::whereIn('training_plan_id', $athletesTrainingPlansIds)
            ->where('start_date', '>=', $maxStartDate->copy()->startOfWeek(Carbon::MONDAY))
            ->get()
            ->groupBy('training_plan_id');

        // Pré-calculer toutes les métriques hebdomadaires en une seule passe
        $bulkWeeklyMetricsData = $this->calculateWeeklyMetricsInBulk($athletes, $maxStartDate, $allMetricsByAthlete, $allTrainingPlanWeeksByAthleteTrainingPlanId, $options['period']);

        // 3. Itérer sur chaque athlète et effectuer les calculs conditionnels
        return $athletes->map(function ($athlete) use ($allMetricsByAthlete, $allTrainingPlanWeeksByAthleteTrainingPlanId, $options, $bulkWeeklyMetricsData) {
            $athleteMetrics = $allMetricsByAthlete->get($athlete->id) ?? collect();
            $athleteTrainingPlansId = $athlete->currentTrainingPlan?->id;
            $athletePlanWeeks = $allTrainingPlanWeeksByAthleteTrainingPlanId->get($athleteTrainingPlansId) ?? collect();
            $athleteWeeklyMetrics = $bulkWeeklyMetricsData->get($athlete->id) ?? collect();

            $athleteData = []; // Données à ajouter à l'athlète

            // Filtrer les métriques par la période demandée par l'utilisateur pour les calculs
            $periodStartDate = $this->calculatePeriodStartDate($options['period']);
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
                    $metricsDataForDashboard[$metricType->value] = $this->prepareSingleMetricDashboardDataFromCollection($metricsForType, $metricType, $options['period']);
                }
                $athleteData['dashboard_metrics_data'] = $metricsDataForDashboard;
            }

            if ($options['include_weekly_metrics']) {
                $weeklyMetricsData = [];
                foreach ($options['calculated_metrics'] as $calculatedMetric) {
                    if ($calculatedMetric === CalculatedMetric::READINESS_SCORE) {
                        continue; // Le score de readiness est géré séparément, pas comme une métrique hebdomadaire ici.
                    }
                    $weeklyMetricsData[$calculatedMetric->value] = $this->prepareWeeklyMetricDashboardDataFromCollection($athlete, $athleteWeeklyMetrics, $calculatedMetric, $options['period']);
                }
                $athleteData['weekly_metrics_data'] = $weeklyMetricsData;
            }

            if ($options['include_latest_daily_metrics']) {
                $athleteData['latest_daily_metrics'] = $this->getAthleteLatestMetricsGroupedByDate($athleteMetrics, $options['period']);
            }

            if (! empty($options['include_alerts'])) {
                $period = $options['period'] ?? 'last_60_days';
                $athleteData['alerts'] = $this->metricAlertsService->getAlerts($athlete, $athleteMetrics, $athletePlanWeeks, $options);
            }

            if ($options['include_menstrual_cycle'] && $athlete->gender->value === 'w') {
                $athleteData['menstrual_cycle_info'] = $this->metricMenstrualService->deduceMenstrualCyclePhase($athlete, $athleteMetrics);
            }

            if ($options['include_readiness_status']) {
                $athleteData['readiness_status'] = $this->metricReadinessService->getAthleteReadinessStatus($athlete, $athleteMetrics);
            }

            // Ajouter les données calculées à l'objet athlète
            foreach ($athleteData as $key => $value) {
                $athlete->{$key} = $value;
            }

            if ($options['chart_metric_type']) {
                $chartMetricType = MetricType::tryFrom($options['chart_metric_type']);
                if ($chartMetricType) {
                    $metricsForChart = $athleteMetrics->where('metric_type', $chartMetricType->value)
                        ->where('date', '>=', $this->calculatePeriodStartDate($options['chart_period']));
                    $athleteData['chart_data'] = $this->prepareSingleMetricChartData($metricsForChart, $chartMetricType);
                }
            }

            return $athlete;
        });
    }

    /**
     * Prépare et calcule en masse les métriques hebdomadaires pour tous les athlètes.
     *
     * @param  Collection<Athlete>  $athletes
     * @param  Collection<int, Collection<Metric>>  $allMetricsByAthlete
     * @param  Collection<int, Collection<TrainingPlanWeek>>  $allTrainingPlanWeeksByAthleteTrainingPlanId
     * @return Collection<int, Collection<string, array>> Collection imbriquée [athlete_id => [week_start_date => [metric_key => value]]]
     */
    protected function calculateWeeklyMetricsInBulk(Collection $athletes, Carbon $maxStartDate, Collection $allMetricsByAthlete, Collection $allTrainingPlanWeeksByAthleteTrainingPlanId, string $period): Collection
    {
        $bulkWeeklyData = collect();
        $now = Carbon::now();
        $startDate = $this->calculatePeriodStartDate($period)->startOfWeek(Carbon::MONDAY);
        $endDate = $now->copy()->endOfWeek(Carbon::SUNDAY);

        $weekPeriod = CarbonPeriod::create($startDate, '1 week', $endDate);

        foreach ($athletes as $athlete) {
            $athleteMetrics = $allMetricsByAthlete->get($athlete->id) ?? collect();
            $athleteTrainingPlansId = $athlete->currentTrainingPlan?->id;
            $athletePlanWeeks = $allTrainingPlanWeeksByAthleteTrainingPlanId->get($athleteTrainingPlansId) ?? collect();

            $athleteWeeklyData = collect();

            foreach ($weekPeriod as $weekStartDate) {
                $weekEndDate = $weekStartDate->copy()->endOfWeek(Carbon::SUNDAY);
                $metricsForWeek = $athleteMetrics->whereBetween('date', [$weekStartDate, $weekEndDate]);

                $cih = $this->metricCalculationService->calculateCihForCollection($metricsForWeek);

                $sbmMetricsForWeek = $metricsForWeek->whereIn('metric_type', [
                    MetricType::MORNING_SLEEP_QUALITY,
                    MetricType::MORNING_GENERAL_FATIGUE,
                    MetricType::MORNING_PAIN,
                    MetricType::MORNING_MOOD_WELLBEING,
                ]);

                $dailySbmValues = $sbmMetricsForWeek->groupBy(fn ($m) => $m->date->format('Y-m-d'))
                    ->map(fn ($dailyMetrics) => $this->metricCalculationService->calculateSbmForCollection($dailyMetrics))
                    ->filter(fn ($value) => $value > 0); // Filtrer les valeurs non valides ou nulles

                $sbm = $dailySbmValues->isNotEmpty() ? $dailySbmValues->avg() : null;

                $planWeek = $athletePlanWeeks->firstWhere('start_date', $weekStartDate->toDateString());
                $cph = $planWeek ? $this->metricCalculationService->calculateCph($planWeek) : 0.0;

                $cihNormalized = $this->metricCalculationService->calculateCihNormalizedForCollection($metricsForWeek);
                $ratioCihCph = ($cih > 0 && $cph > 0) ? $this->metricCalculationService->calculateRatio($cih, $cph) : null;
                $ratioCihNormalizedCph = ($cihNormalized > 0 && $cph > 0) ? $this->metricCalculationService->calculateRatio($cihNormalized, $cph) : null;

                $athleteWeeklyData->put($weekStartDate->toDateString(), [
                    CalculatedMetric::CIH->value                      => $cih,
                    CalculatedMetric::SBM->value                      => $sbm,
                    CalculatedMetric::CPH->value                      => $cph,
                    CalculatedMetric::CIH_NORMALIZED->value           => $cihNormalized,
                    CalculatedMetric::RATIO_CIH_CPH->value            => $ratioCihCph,
                    CalculatedMetric::RATIO_CIH_NORMALIZED_CPH->value => $ratioCihNormalizedCph,
                ]);
            }
            $bulkWeeklyData->put($athlete->id, $athleteWeeklyData);
        }

        return $bulkWeeklyData;
    }

    protected function determineMetricCollectionStartDate(array $options): Carbon
    {
        $now = Carbon::now();
        $startDate = $this->calculatePeriodStartDate($options['period']);

        // Si les tendances sont incluses, s'assurer d'avoir au moins 60 jours de données
        if ($options['include_dashboard_metrics'] || $options['include_weekly_metrics'] || ! empty($options['include_alerts'])) {
            $startDate = $startDate->min($now->copy()->subDays(60)->startOfDay());
        }

        return $startDate;
    }

    /**
     * Get latest metrics grouped by date for a collection of metrics.
     */
    protected function getAthleteLatestMetricsGroupedByDate(Collection $athleteMetrics, string $period): Collection
    {
        $startDate = $this->calculatePeriodStartDate($period);
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
     * Prépare les données d'une métrique pour le tableau de bord à partir d'une collection pré-filtrée.
     */
    protected function prepareSingleMetricDashboardDataFromCollection(Collection $metricsForPeriod, MetricType $metricType, string $period): array
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

        $metricData['chart_data'] = $this->prepareSingleMetricChartData($metricsForPeriod, $metricType);

        $lastMetric = $metricsForPeriod->sortByDesc('date')->first();
        if ($lastMetric) {
            $metricValue = $lastMetric->{$valueColumn};
            $metricData['last_value'] = $metricValue;
            $metricData['formatted_last_value'] = $this->formatMetricDisplayValue($metricValue, $metricType);
        }

        if ($metricData['is_numerical']) {
            $trends = $this->metricTrendsService->calculateMetricAveragesFromCollection($metricsForPeriod, $metricType);
            $metricData['average_7_days'] = $trends['averages']['Derniers 7 jours'] ?? null;
            $metricData['average_30_days'] = $trends['averages']['Derniers 30 jours'] ?? null;
            $metricData['formatted_average_7_days'] = $this->formatMetricDisplayValue($metricData['average_7_days'], $metricType);
            $metricData['formatted_average_30_days'] = $this->formatMetricDisplayValue($metricData['average_30_days'], $metricType);

            $evolutionTrendData = $this->metricTrendsService->calculateMetricEvolutionTrend($metricsForPeriod, $metricType);
            if ($metricData['average_7_days'] !== null && $evolutionTrendData['trend'] !== 'N/A') {
                $optimalDirection = $metricType->getTrendOptimalDirection();

                $metricData['trend_icon'] = $this->getTrendIcon($evolutionTrendData['trend']);

                $metricData['trend_color'] = $this->determineTrendColor($evolutionTrendData['trend'], $optimalDirection);
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
     *
     * @param  Collection<object>  $athleteWeeklyMetrics  Les données hebdomadaires pré-calculées pour l'athlète.
     */
    protected function prepareWeeklyMetricDashboardDataFromCollection(Athlete $athlete, Collection $athleteWeeklyMetrics, CalculatedMetric $metricKey, string $period): array
    {
        $now = Carbon::now();
        $allWeeklyData = new Collection;

        // Filtrer les données hebdomadaires pré-calculées pour la métrique spécifique
        foreach ($athleteWeeklyMetrics as $weekStartDateStr => $weeklyData) {
            $weekStartDate = Carbon::parse($weekStartDateStr);
            $value = $weeklyData[$metricKey->value] ?? null;
            if (is_numeric($value)) {
                $allWeeklyData->push((object) ['date' => $weekStartDate->copy(), 'value' => $value]);
            }
        }

        // Calcule les statistiques pour le tableau de bord à partir des données hebdomadaires
        $lastValue = $allWeeklyData->sortByDesc('date')->first()->value ?? null;

        $dataFor7Days = $allWeeklyData->filter(fn ($item) => $item->date->greaterThanOrEqualTo($now->copy()->subDays(7)->startOfWeek(Carbon::MONDAY)));
        $average7Days = $dataFor7Days->isNotEmpty() ? $dataFor7Days->avg('value') : null;

        $dataFor30Days = $allWeeklyData->filter(fn ($item) => $item->date->greaterThanOrEqualTo($now->copy()->subDays(30)->startOfWeek(Carbon::MONDAY)));
        $average30Days = $dataFor30Days->isNotEmpty() ? $dataFor30Days->avg('value') : null;

        $trend = $this->metricTrendsService->calculateGenericNumericTrend($allWeeklyData);
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
            'trend_icon'                => $trend['trend'] !== 'N/A' ? $this->getTrendIcon($trend['trend']) : 'ellipsis-horizontal',
            'trend_color' => $trend['trend'] !== 'N/A' ? $this->determineTrendColor($trend['trend'], $metricKey->getTrendOptimalDirection()) : 'zinc',
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
    public function calculatePeriodStartDate(string $period): Carbon
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
    public function retrieveAthleteRawMetrics(Athlete $athlete, array $filters = []): Collection
    {
        $query = $athlete->metrics()->orderBy('date');

        if (isset($filters['metric_type']) && $filters['metric_type'] !== 'all') {
            if (MetricType::tryFrom($filters['metric_type'])) {
                $query->where('metric_type', $filters['metric_type']);
            }
        }

        if (isset($filters['period'])) {
            $this->applyMetricPeriodFilterToQuery($query, $filters['period']);
        }

        return $query->get();
    }

    /**
     * Applique le filtre de période à la requête.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $period  (e.g., 'last_7_days', 'last_30_days', 'last_6_months', 'last_year', 'all_time', 'custom:start_date,end_date')
     */
    protected function applyMetricPeriodFilterToQuery($query, string $period): void
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
    public function prepareSingleMetricChartData(Collection $metrics, MetricType $metricType): array
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
    public function prepareMultipleMetricsChartData(Collection $metrics, array $metricTypes): array
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
    public function getRecentMetricsGroupedByDate(Athlete $athlete, int $limit = 50): Collection
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
    public function getAthleteMetricDashboardSummary(Athlete $athlete, MetricType $metricType, string $period): array
    {
        $metricsForPeriod = $this->retrieveAthleteRawMetrics($athlete, ['metric_type' => $metricType->value, 'period' => $period]);

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

        $metricData['chart_data'] = $this->prepareSingleMetricChartData($metricsForPeriod, $metricType);

        $lastMetric = $metricsForPeriod->sortByDesc('date')->first();
        if ($lastMetric) {
            $metricValue = $lastMetric->{$valueColumn};
            $metricData['last_value'] = $metricValue;
            $metricData['formatted_last_value'] = $this->formatMetricDisplayValue($metricValue, $metricType);
        }

        if ($metricData['is_numerical']) {
            $trends = $this->metricTrendsService->calculateMetricAveragesFromCollection($metricsForPeriod, $metricType);

            $metricData['average_7_days'] = $trends['averages']['Derniers 7 jours'] ?? null;
            $metricData['average_30_days'] = $trends['averages']['Derniers 30 jours'] ?? null;

            $metricData['formatted_average_7_days'] = $this->formatMetricDisplayValue($metricData['average_7_days'], $metricType);
            $metricData['formatted_average_30_days'] = $this->formatMetricDisplayValue($metricData['average_30_days'], $metricType);

            $evolutionTrendData = $this->metricTrendsService->calculateMetricEvolutionTrend($metricsForPeriod, $metricType);

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
                $metricData['trend_percentage'] = $this->formatMetricDisplayValue($metricData['average_7_days'], $metricType);
            }
        }

        return $metricData;
    }

    /**
     * Formate une valeur de métrique en fonction de son type et de sa précision.
     */
    public function formatMetricDisplayValue(mixed $value, MetricType $metricType): string
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
     * Récupère la TrainingPlanWeek pour un athlète et une date de début de semaine donnés.
     */
    public function retrieveAthleteTrainingPlanWeek(Athlete $athlete, Carbon $weekStartDate): ?TrainingPlanWeek
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
     * Détermine la couleur de la tendance en fonction de la direction de la tendance et de la direction optimale.
     */
    protected function determineTrendColor(string $trend, string $optimalDirection): string
    {
        return match ($trend) {
            'increasing' => ($optimalDirection === 'good' ? 'lime' : ($optimalDirection === 'bad' ? 'rose' : 'zinc')),
            'decreasing' => ($optimalDirection === 'good' ? 'rose' : ($optimalDirection === 'bad' ? 'lime' : 'zinc')),
            default      => 'zinc', // stable
        };
    }

    /**
     * Détermine l'icône de tendance en fonction de la direction de la tendance.
     */
    protected function getTrendIcon(string $trend): string
    {
        return match ($trend) {
            'increasing' => 'arrow-trending-up',
            'decreasing' => 'arrow-trending-down',
            default      => 'minus', // stable ou N/A
        };
    }
}
