<?php

namespace App\Services;

use App\Models\Metric;
use App\Models\Athlete;
use App\Enums\MetricType;
use Illuminate\Support\Carbon;
use App\Models\CalculatedMetric;
use App\Models\TrainingPlanWeek;
use Illuminate\Support\Collection;
use App\Enums\CalculatedMetricType;

class MetricService
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
            'calculated_metrics'           => CalculatedMetricType::cases(),
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

        if (in_array('general', $options['include_alerts'])) {
            $existingMetricValues = array_map(fn ($metricType) => $metricType->value, $metricTypes);
            $painMetricValues = array_map(fn ($metricType) => $metricType->value, $this->metricAlertsService::PAIN_METRICS);

            $combinedMetricValues = array_unique(array_merge($existingMetricValues, $painMetricValues));
            $metricTypes = array_map(fn ($value) => MetricType::from($value), $combinedMetricValues);
        }

        // Déterminer la date de début maximale pour la collecte des métriques brutes, en fonction de la période et des options d'inclusion.
        $maxStartDate = $this->determineMetricCollectionStartDate($options);

        // Pré-charger toutes les métriques brutes, calculées et les semaines de plan d'entraînement pour tous les athlètes.
        $allMetricsByAthlete = Metric::whereIn('athlete_id', $athleteIds)
            ->where('date', '>=', $maxStartDate)
            ->whereIn('metric_type', array_map(fn ($metricType) => $metricType->value, $metricTypes))
            ->orderBy('date', 'asc')
            ->get()
            ->groupBy('athlete_id');

        if ($options['include_menstrual_cycle']) {
            $allMetricsByAthlete = $allMetricsByAthlete->mergeRecursive(
                Metric::whereIn('athlete_id', $athleteIds)
                    ->where('date', '>=', now()->copy()->subYears(2)->startOfDay())
                    ->where('metric_type', MetricType::MORNING_FIRST_DAY_PERIOD)
                    ->get()
                    ->groupBy('athlete_id')
            );
        }

        $allCalculatedMetricsByAthlete = CalculatedMetric::whereIn('athlete_id', $athleteIds)
            ->where('date', '>=', $maxStartDate)
            ->get()
            ->groupBy('athlete_id');

        $athletesTrainingPlansIds = $athletes->pluck('current_training_plan.id')->filter()->values();
        $allTrainingPlanWeeksByAthleteTrainingPlanId = TrainingPlanWeek::whereIn('training_plan_id', $athletesTrainingPlansIds)
            ->where('start_date', '>=', $maxStartDate->copy()->startOfWeek(Carbon::MONDAY))
            ->get()
            ->groupBy('training_plan_id');

        // Itérer sur chaque athlète et effectuer les calculs conditionnels
        return $athletes->map(function ($athlete) use ($allMetricsByAthlete, $allCalculatedMetricsByAthlete, $allTrainingPlanWeeksByAthleteTrainingPlanId, $options) {
            $athleteMetrics = $allMetricsByAthlete->get($athlete->id) ?? collect();
            $athleteCalculatedMetrics = $allCalculatedMetricsByAthlete->get($athlete->id) ?? collect();
            $athleteTrainingPlansId = $athlete->currentTrainingPlan?->id;
            $athletePlanWeeks = $allTrainingPlanWeeksByAthleteTrainingPlanId->get($athleteTrainingPlansId) ?? collect();

            $athleteData = []; // Données à ajouter à l'athlète

            $periodStartDate = $this->calculatePeriodStartDate($options['period']);
            $filteredAthleteMetrics = $athleteMetrics->where('date', '>=', $periodStartDate);
            $filteredAthleteCalculatedMetrics = $athleteCalculatedMetrics->where('date', '>=', $periodStartDate);

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
                    if ($calculatedMetric === CalculatedMetricType::READINESS_SCORE) {
                        continue; // Le score de readiness est géré séparément, pas comme une métrique hebdomadaire ici.
                    }
                    $weeklyMetricsData[$calculatedMetric->value] = $this->prepareWeeklyMetricDashboardDataFromCollection($athlete, $filteredAthleteCalculatedMetrics, $calculatedMetric, $options['period']);
                }
                $athleteData['weekly_metrics_data'] = $weeklyMetricsData;
            }

            if ($options['include_latest_daily_metrics']) {
                $athleteData['latest_daily_metrics'] = $this->getAthleteLatestMetricsGroupedByDate($athleteMetrics, $options['period']);
            }

            if (! empty($options['include_alerts'])) {
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

    protected function determineMetricCollectionStartDate(array $options): Carbon
    {
        $now = Carbon::now();
        $startDate = $this->calculatePeriodStartDate($options['period']);

        // Si les tendances sont incluses, s'assurer d'avoir au moins 60 jours de données
        if ($options['include_dashboard_metrics'] || $options['include_weekly_metrics'] || ! empty($options['include_alerts'])) {
            $startDate = $startDate->min($now->copy()->subDays(90)->startOfDay());
        }

        return $startDate;
    }

    protected function getAthleteLatestMetricsGroupedByDate(Collection $athleteMetrics, string $period): Collection
    {
        $startDate = $this->calculatePeriodStartDate($period);
        $endDate = now()->endOfDay();

        $filteredMetrics = $athleteMetrics->filter(fn (Metric $metric) => $metric->date->between($startDate, $endDate));

        return $filteredMetrics->sortByDesc('date')->groupBy(fn (Metric $metric) => $metric->date->format('Y-m-d'));
    }

    protected function prepareSingleMetricDashboardDataFromCollection(Collection $metricsForPeriod, MetricType $metricType, string $period): array
    {
        $valueColumn = $metricType->getValueColumn();
        $metricData = [
            'label'                     => $metricType->getLabel(),
            'short_label'               => $metricType->getLabelShort(),
            'description'               => $metricType->getDescription(),
            'unit'                      => $metricType->getUnit(),
            'last_value'                => null,
            'formatted_last_value'      => 'n/a',
            'last_value_date'           => null,
            'is_last_value_today'       => false,
            'average_7_days'            => null,
            'formatted_average_7_days'  => 'n/a',
            'average_30_days'           => null,
            'formatted_average_30_days' => 'n/a',
            'trend_icon'                => 'ellipsis-horizontal',
            'trend_color'               => 'zinc',
            'trend_percentage'          => 'n/a',
            'chart_data'                => [],
            'is_numerical'              => ($valueColumn !== 'note'),
        ];

        $metricData['chart_data'] = $this->prepareSingleMetricChartData($metricsForPeriod, $metricType);

        $lastMetric = $metricsForPeriod->sortByDesc('date')->first();
        if ($lastMetric) {
            $metricValue = $lastMetric->{$valueColumn};
            $metricData['last_value'] = $metricValue;
            $metricData['formatted_last_value'] = $this->formatMetricDisplayValue($metricValue, $metricType);
            $metricData['last_value_date'] = $lastMetric->date;
            $metricData['is_last_value_today'] = $lastMetric->date->isToday();
        }

        if ($metricData['is_numerical']) {
            $trends = $this->metricTrendsService->calculateMetricAveragesFromCollection($metricsForPeriod, $metricType);
            $metricData['average_7_days'] = $trends['averages']['Derniers 7 jours'] ?? null;
            $metricData['average_30_days'] = $trends['averages']['Derniers 30 jours'] ?? null;
            $metricData['formatted_average_7_days'] = $this->formatMetricDisplayValue($metricData['average_7_days'], $metricType);
            $metricData['formatted_average_30_days'] = $this->formatMetricDisplayValue($metricData['average_30_days'], $metricType);

            $evolutionTrendData = $this->metricTrendsService->calculateMetricEvolutionTrend($metricsForPeriod, $metricType);
            if ($metricData['average_7_days'] !== null && $evolutionTrendData['trend'] !== 'n/a') {
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

    protected function prepareWeeklyMetricDashboardDataFromCollection(Athlete $athlete, Collection $athleteCalculatedMetrics, CalculatedMetricType $metricType, string $period): array
    {
        $now = Carbon::now();

        $weeklyData = $athleteCalculatedMetrics
            ->where('type', $metricType->value)
            ->groupBy(fn ($metric) => $metric->date->startOfWeek()->format('Y-m-d'))
            ->map(fn (Collection $weekMetrics) => $weekMetrics->avg('value'));

        $lastWeekDate = $weeklyData->keys()->sortDesc()->first();
        $lastValue = $lastWeekDate ? $weeklyData[$lastWeekDate] : null;

        $dataFor7Days = $weeklyData->filter(fn ($value, $dateStr) => Carbon::parse($dateStr)->greaterThanOrEqualTo($now->copy()->subDays(7)));
        $average7Days = $dataFor7Days->isNotEmpty() ? $dataFor7Days->avg() : null;

        $dataFor30Days = $weeklyData->filter(fn ($value, $dateStr) => Carbon::parse($dateStr)->greaterThanOrEqualTo($now->copy()->subDays(30)));
        $average30Days = $dataFor30Days->isNotEmpty() ? $dataFor30Days->avg() : null;

        $trend = $this->metricTrendsService->calculateGenericNumericTrend($athleteCalculatedMetrics->where('type', $metricType));
        $changePercentage = 'n/a';
        if (is_numeric($average7Days) && is_numeric($average30Days) && $average30Days != 0) {
            $change = (($average7Days - $average30Days) / $average30Days) * 100;
            $changePercentage = ($change > 0 ? '+' : '').number_format($change, 1).'%';
        }

        return [
            'label'                     => $metricType->getLabelShort(),
            'formatted_last_value'      => is_numeric($lastValue) ? number_format($lastValue, 1) : 'n/a',
            'last_value_date'           => $lastWeekDate ? Carbon::parse($lastWeekDate) : null,
            'is_last_value_today'       => $lastWeekDate ? Carbon::parse($lastWeekDate)->isSameWeek(now()) : false,
            'formatted_average_7_days'  => is_numeric($average7Days) ? number_format($average7Days, 1) : 'n/a',
            'formatted_average_30_days' => is_numeric($average30Days) ? number_format($average30Days, 1) : 'n/a',
            'is_numerical'              => true,
            'trend_icon'                => $trend['trend'] !== 'n/a' ? $this->getTrendIcon($trend['trend']) : 'ellipsis-horizontal',
            'trend_color'               => $trend['trend'] !== 'n/a' ? $this->determineTrendColor($trend['trend'], $metricType->getTrendOptimalDirection()) : 'zinc',
            'trend_percentage'          => $changePercentage,
            'chart_data'                => [
                'labels' => $weeklyData->keys()->map(fn ($d) => Carbon::parse($d)->format('W Y'))->all(),
                'data'   => $weeklyData->values()->all(),
            ],
        ];
    }

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
            'formatted_last_value'      => 'n/a',
            'last_value_date'           => null,
            'is_last_value_today'       => false,
            'average_7_days'            => null,
            'formatted_average_7_days'  => 'n/a',
            'average_30_days'           => null,
            'formatted_average_30_days' => 'n/a',
            'trend_icon'                => 'ellipsis-horizontal',
            'trend_color'               => 'zinc',
            'trend_percentage'          => 'n/a',
            'chart_data'                => [],
            'is_numerical'              => ($valueColumn !== 'note'),
        ];

        $metricData['chart_data'] = $this->prepareSingleMetricChartData($metricsForPeriod, $metricType);

        $lastMetric = $metricsForPeriod->sortByDesc('date')->first();
        if ($lastMetric) {
            $metricValue = $lastMetric->{$valueColumn};
            $metricData['last_value'] = $metricValue;
            $metricData['formatted_last_value'] = $this->formatMetricDisplayValue($metricValue, $metricType);
            $metricData['last_value_date'] = $lastMetric->date;
            $metricData['is_last_value_today'] = $lastMetric->date->isToday();
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

    public function formatMetricDisplayValue(mixed $value, MetricType $metricType): string
    {
        if ($value === null) {
            return 'n/a';
        }
        if ($metricType->getValueColumn() === 'note') {
            return (string) $value;
        }

        $formattedValue = number_format($value, $metricType->getPrecision());
        $unit = $metricType->getUnit();
        $scale = $metricType->getScale();

        return $formattedValue.($unit ? ' '.$unit : '').($scale ? '/'.$scale : '');
    }

    public function retrieveAthleteTrainingPlanWeek(Athlete $athlete, Carbon $weekStartDate): ?TrainingPlanWeek
    {
        $assignedPlan = $athlete->currentTrainingPlan;

        if (! $assignedPlan) {
            return null;
        }

        return TrainingPlanWeek::where('training_plan_id', $assignedPlan->id)
            ->where('start_date', $weekStartDate)
            ->first();
    }

    protected function determineTrendColor(string $trend, string $optimalDirection): string
    {
        return match ($trend) {
            'increasing' => ($optimalDirection === 'good' ? 'lime' : ($optimalDirection === 'bad' ? 'rose' : 'zinc')),
            'decreasing' => ($optimalDirection === 'good' ? 'rose' : ($optimalDirection === 'bad' ? 'lime' : 'zinc')),
            default      => 'zinc', // stable
        };
    }

    protected function getTrendIcon(string $trend): string
    {
        return match ($trend) {
            'increasing' => 'arrow-trending-up',
            'decreasing' => 'arrow-trending-down',
            default      => 'minus', // stable ou n/a
        };
    }

    public function prepareDailyMetricsForTableView(Collection $latestDailyMetrics, array $displayTableMetricTypes, Athlete $athlete, bool $isTrainerContext = false): Collection
    {
        return $latestDailyMetrics->map(function ($metricDates, $date) use ($displayTableMetricTypes, $athlete, $isTrainerContext) {
            $rowData = [
                'date'      => \Carbon\Carbon::parse($date)->locale('fr_CH')->isoFormat('L'),
                'metrics'   => [],
                'edit_link' => null,
            ];

            foreach ($displayTableMetricTypes as $metricType) {
                $metric = $metricDates->where('metric_type', $metricType->value)->first();
                $rowData['metrics'][$metricType->value] = $metric ? $this->formatMetricDisplayValue($metric->{$metricType->getValueColumn()}, $metricType) : 'n/a';
            }

            if ($isTrainerContext) {
                $firstMetricOfDay = $metricDates->first();
                if ($firstMetricOfDay && isset($firstMetricOfDay->metadata['edit_link'])) {
                    $rowData['edit_link'] = $firstMetricOfDay->metadata['edit_link'];
                } elseif ($firstMetricOfDay) {
                    $rowData['edit_link'] = null;
                }
            } else {
                $rowData['edit_link'] = route('athletes.metrics.daily.form', ['hash' => $athlete->hash, 'd' => $date]);
            }

            return $rowData;
        });
    }
}
