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
            'endDate'                      => null,
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
        $endDate = $options['endDate'] ? Carbon::parse($options['endDate']) : Carbon::now();

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
            $metricTypes = collect($metricTypes)
                ->merge($this->metricReadinessService::ESSENTIAL_DAILY_READINESS_METRICS)
                ->unique()
                ->values()
                ->all();
        }

        if (in_array('general', $options['include_alerts'])) {
            $metricTypes = collect($metricTypes)
                ->merge($this->metricAlertsService::PAIN_METRICS)
                ->unique()
                ->values()
                ->all();
        }

        // Déterminer la date de début maximale pour la collecte des métriques brutes, en fonction de la période et des options d'inclusion.
        $maxStartDate = $this->determineMetricCollectionStartDate($options, $endDate);

        // Pré-charger toutes les métriques brutes, calculées et les semaines de plan d'entraînement pour tous les athlètes.
        $allMetricsByAthlete = Metric::whereIn('athlete_id', $athleteIds)
            ->where('date', '>=', $maxStartDate)
            ->whereIn('metric_type', collect($metricTypes)->pluck('value')->all())
            ->orderBy('date', 'asc')
            ->get()
            ->groupBy('athlete_id');

        if ($options['include_menstrual_cycle']) {
            $menstrualMetricsByAthlete = Metric::whereIn('athlete_id', $athleteIds)
                ->where('date', '>=', $endDate->copy()->subYears(2)->startOfDay())
                ->where('metric_type', MetricType::MORNING_FIRST_DAY_PERIOD)
                ->get()
                ->groupBy('athlete_id');

            $allAthleteIds = $allMetricsByAthlete->keys()->union($menstrualMetricsByAthlete->keys());

            $allMetricsByAthlete = $allAthleteIds->mapWithKeys(function ($athleteId) use ($allMetricsByAthlete, $menstrualMetricsByAthlete) {
                $regularMetrics = $allMetricsByAthlete->get($athleteId, collect());
                $menstrualMetrics = $menstrualMetricsByAthlete->get($athleteId, collect());

                return [$athleteId => $regularMetrics->merge($menstrualMetrics)->sortBy('date')];
            });
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
        return $athletes->map(function ($athlete) use ($allMetricsByAthlete, $allCalculatedMetricsByAthlete, $allTrainingPlanWeeksByAthleteTrainingPlanId, $options, $endDate) {
            $athleteMetrics = $allMetricsByAthlete->get($athlete->id) ?? collect();
            $athleteCalculatedMetrics = $allCalculatedMetricsByAthlete->get($athlete->id) ?? collect();
            $athleteTrainingPlansId = $athlete->currentTrainingPlan?->id;
            $athletePlanWeeks = $allTrainingPlanWeeksByAthleteTrainingPlanId->get($athleteTrainingPlansId) ?? collect();

            $athleteData = []; // Données à ajouter à l'athlète

            $periodStartDate = $this->calculatePeriodStartDate($options['period'], $endDate);
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
                    $metricsForType = $athleteMetrics->where('metric_type', $metricType->value);
                    $metricsDataForDashboard[$metricType->value] = $this->prepareSingleMetricDashboardDataFromCollection($metricsForType, $metricType, $options['period'], $endDate);
                }
                $athleteData['dashboard_metrics_data'] = $metricsDataForDashboard;
            }

            if ($options['include_weekly_metrics']) {
                $weeklyMetricsData = [];
                foreach ($options['calculated_metrics'] as $calculatedMetric) {
                    $weeklyMetricsData[$calculatedMetric->value] = $this->prepareWeeklyMetricDashboardDataFromCollection($athlete, $filteredAthleteCalculatedMetrics, $calculatedMetric, $options['period'], $endDate);
                }
                $athleteData['weekly_metrics_data'] = $weeklyMetricsData;
            }

            if ($options['include_latest_daily_metrics']) {
                $athleteData['latest_daily_metrics'] = $this->getAthleteLatestMetricsGroupedByDate($filteredAthleteMetrics);
            }

            if (! empty($options['include_alerts'])) {
                $athleteData['alerts'] = $this->metricAlertsService->getAlerts($athlete, $filteredAthleteMetrics, $athletePlanWeeks, $options);
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

            $athlete->weekly_badges_by_metric = $this->generateWeeklyStatusBadges(collect($athlete->weekly_metrics_data ?? []));

            if ($options['chart_metric_type']) {
                $chartMetricType = MetricType::tryFrom($options['chart_metric_type']);
                if ($chartMetricType) {
                    $metricsForChart = $athleteMetrics->where('metric_type', $chartMetricType->value)
                        ->where('date', '>=', $this->calculatePeriodStartDate($options['chart_period'], $endDate));
                    $athleteData['chart_data'] = $this->prepareSingleMetricChartData($metricsForChart, $chartMetricType);
                }
            }

            return $athlete;
        });
    }

    protected function generateWeeklyStatusBadges(Collection $allMetrics): array
    {
        $badges = [];

        // 1. Badge ACWR
        $acwrData = $allMetrics->get(CalculatedMetricType::ACWR->value);
        if ($acwrData && is_numeric($acwrData['latest_daily_value'])) {
            $acwr = $acwrData['latest_daily_value'];
            $status = 'neutral';
            $summary = 'ACWR: '.number_format($acwr, 2);
            if ($acwr >= 1.5) {
                $status = 'critical';
                $summary = 'ACWR: Risque élevé';
            } elseif ($acwr >= 1.3) {
                $status = 'warning';
                $summary = 'ACWR: Zone à risque';
            } elseif ($acwr < 0.8 && $acwr > 0) {
                $status = 'low_risk';
                $summary = 'ACWR: Désadaptation';
            } elseif ($acwr >= 0.8) {
                $status = 'optimal';
                $summary = 'ACWR: Idéal';
            }
            $badges[CalculatedMetricType::ACWR->value] = ['status' => $status, 'summary' => $summary, 'value' => number_format($acwr, 2)];
        }

        // 2. Badge Adhérence à la charge (ratio CIH normalisé / CPH)
        $ratioCihCphData = $allMetrics->get(CalculatedMetricType::RATIO_CIH_NORMALIZED_CPH->value);
        if ($ratioCihCphData && is_numeric($ratioCihCphData['latest_daily_value'])) {
            $ratio = $ratioCihCphData['latest_daily_value'];
            $status = 'neutral';
            $summary = 'Adhésion: '.number_format($ratio, 2);
            if ($ratio > 1.3) {
                $status = 'warning';
                $summary = 'Adhésion: Surcharge';
            } elseif ($ratio < 0.7 && $ratio > 0) {
                $status = 'warning';
                $summary = 'Adhésion: Sous-charge';
            } elseif ($ratio >= 0.7) {
                $status = 'optimal';
                $summary = 'Adhésion: Idéale';
            }
            $badges[CalculatedMetricType::RATIO_CIH_NORMALIZED_CPH->value] = ['status' => $status, 'summary' => $summary, 'value' => number_format($ratio, 2)];
        }

        // 3. Badge Dette de récupération (SBM)
        $sbmData = $allMetrics->get(CalculatedMetricType::SBM->value);
        if ($sbmData && is_numeric($sbmData['formatted_average_7_days']) && is_numeric($sbmData['formatted_average_30_days']) && $sbmData['formatted_average_30_days'] > 0) {
            $sbmAvg7d = $sbmData['formatted_average_7_days'];
            $sbmAvg30d = $sbmData['formatted_average_30_days'];
            $diffPercent = (($sbmAvg7d - $sbmAvg30d) / $sbmAvg30d) * 100;
            $status = 'optimal';
            $summary = 'Récupération: Équilibre';
            if ($diffPercent < -5) {
                $status = 'warning';
                $summary = 'Récupération: En dette';
            }
            $badges[CalculatedMetricType::SBM->value] = ['status' => $status, 'summary' => $summary, 'value' => number_format($diffPercent, 0).'%'];
        }

        // 3. Readiness
        $readdinessData = $allMetrics->get(CalculatedMetricType::READINESS_SCORE->value);
        if ($readdinessData && is_numeric($readdinessData['latest_daily_value'])) {
            $readinessScore = $readdinessData['latest_daily_value'];
            $status = 'neutral';
            $summary = 'Score: Score non calculable.';
            if ($readinessScore < 50) {
                $status = 'critical';
                $summary = 'Readiness: Risque accru de fatigue ou blessure.';
            } elseif ($readinessScore < 70) {
                $status = 'warning';
                $summary = 'Readiness: Signes de fatigue ou de stress.';
            } elseif ($readinessScore < 80) {
                $status = 'low_risk';
                $summary = 'Readiness: Rester attentif aux sensations et adapter si nécessaire.';
            } elseif ($acwr < 100) {
                $status = 'optimal';
                $summary = 'Readiness: Idéal';
            }
            $badges[CalculatedMetricType::READINESS_SCORE->value] = ['status' => $status, 'summary' => $summary, 'value' => number_format($acwr, 2)];
        }

        return $badges;
    }

    protected function determineMetricCollectionStartDate(array $options, Carbon $endDate): Carbon
    {
        $startDate = $this->calculatePeriodStartDate($options['period'], $endDate);

        // Si les tendances sont incluses, s'assurer d'avoir au moins 60 jours de données
        if ($options['include_dashboard_metrics'] || $options['include_weekly_metrics'] || ! empty($options['include_alerts'])) {
            $startDate = $startDate->min($endDate->copy()->subDays(90)->startOfDay());
        }

        return $startDate;
    }

    protected function getAthleteLatestMetricsGroupedByDate(Collection $filteredMetrics): Collection
    {
        return $filteredMetrics->sortByDesc('date')->groupBy(fn (Metric $metric) => $metric->date->format('Y-m-d'));
    }

    protected function prepareSingleMetricDashboardDataFromCollection(Collection $allMetricsForType, MetricType $metricType, string $period, Carbon $endDate): array
    {
        $valueColumn = $metricType->getValueColumn();

        $periodStartDate = $this->calculatePeriodStartDate($period, $endDate);
        $metricsForPeriod = $allMetricsForType->where('date', '>=', $periodStartDate);

        $metricData = [
            'label'                           => $metricType->getLabel(),
            'short_label'                     => $metricType->getLabelShort(),
            'description'                     => $metricType->getDescription(),
            'unit'                            => $metricType->getUnit(),
            'latest_daily_value'              => null,
            'formatted_latest_daily_value'    => 'n/a',
            'latest_daily_value_date'         => null,
            'is_latest_daily_value_today'     => false,
            'latest_weekly_average'           => null,
            'formatted_latest_weekly_average' => 'n/a',
            'average_7_days'                  => null,
            'formatted_average_7_days'        => 'n/a',
            'average_30_days'                 => null,
            'formatted_average_30_days'       => 'n/a',
            'trend_icon'                      => 'ellipsis-horizontal',
            'trend_color'                     => 'zinc',
            'trend_percentage'                => 'n/a',
            'chart_data'                      => [],
            'is_numerical'                    => ($valueColumn !== 'note'),
        ];

        $metricData['chart_data'] = $this->prepareSingleMetricChartData($metricsForPeriod, $metricType);

        $lastMetric = $metricsForPeriod->sortByDesc('date')->first();
        if ($lastMetric) {
            $metricValue = $lastMetric->{$valueColumn};
            $metricData['latest_daily_value'] = $metricValue;
            $metricData['formatted_latest_daily_value'] = $this->formatMetricDisplayValue($metricValue, $metricType);
            $metricData['latest_daily_value_date'] = $lastMetric->date;
            $metricData['is_latest_daily_value_today'] = $lastMetric->date->isSameDay($endDate);
        }

        if ($metricData['is_numerical']) {
            // Calculate latest weekly average for raw metrics
            $weeklyData = $allMetricsForType
                ->where('date', '>=', $endDate->copy()->startOfWeek(Carbon::MONDAY)) // Only current week for average
                ->where('date', '<=', $endDate)
                ->whereNotNull($valueColumn)
                ->groupBy(fn ($metric) => $metric->date->startOfWeek(Carbon::MONDAY)->format('Y-m-d'))
                ->map(fn (Collection $weekMetrics) => $weekMetrics->avg($valueColumn));

            $latestWeeklyAverageDateKey = $weeklyData->keys()->sortDesc()->first();
            $latestWeeklyAverage = $latestWeeklyAverageDateKey ? $weeklyData[$latestWeeklyAverageDateKey] : null;

            $metricData['latest_weekly_average'] = $latestWeeklyAverage;
            $metricData['formatted_latest_weekly_average'] = $this->formatMetricDisplayValue($latestWeeklyAverage, $metricType);

            $trends = $this->metricTrendsService->calculateMetricAveragesFromCollection($allMetricsForType, $metricType);
            $metricData['average_7_days'] = $trends['averages']['Derniers 7 jours'] ?? null;
            $metricData['average_30_days'] = $trends['averages']['Derniers 30 jours'] ?? null;
            $metricData['formatted_average_7_days'] = $this->formatMetricDisplayValue($metricData['average_7_days'], $metricType);
            $metricData['formatted_average_30_days'] = $this->formatMetricDisplayValue($metricData['average_30_days'], $metricType);

            $evolutionTrendData = $this->metricTrendsService->calculateMetricEvolutionTrend($allMetricsForType, $metricType);
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

    protected function prepareWeeklyMetricDashboardDataFromCollection(Athlete $athlete, Collection $athleteCalculatedMetrics, CalculatedMetricType $metricType, string $period, Carbon $endDate): array
    {
        // --- NEW LOGIC: Determine the latest daily calculated value ---
        $latestDailyCalculatedMetric = $athleteCalculatedMetrics
            ->where('type', $metricType->value)
            ->where('date', '<=', $endDate->copy()->endOfDay()) // Consider values up to the end of the current end date
            ->sortByDesc('date')
            ->first();

        $latestDailyValue = $latestDailyCalculatedMetric ? $latestDailyCalculatedMetric->value : null;
        $latestDailyValueDate = $latestDailyCalculatedMetric ? $latestDailyCalculatedMetric->date : null;
        // --- END NEW LOGIC ---

        $trendsData = $this->metricTrendsService->calculateCalculatedMetricAveragesFromCollection($athleteCalculatedMetrics->where('type', $metricType->value), $metricType);
        $weeklyData = $athleteCalculatedMetrics
            ->where('type', $metricType->value)
            ->groupBy(fn ($metric) => $metric->date->startOfWeek(Carbon::MONDAY)->format('Y-m-d')) // Group by Monday for consistent weeks
            ->map(fn (Collection $weekMetrics) => $weekMetrics->avg('value'));

        // The previous $lastWeekDate and $lastValue represented the average of the last week.
        // Let's rename them for clarity.
        $latestWeeklyAverageDateKey = $weeklyData->keys()->sortDesc()->first();
        $latestWeeklyAverage = $latestWeeklyAverageDateKey ? $weeklyData[$latestWeeklyAverageDateKey] : null;

        $trend = $this->metricTrendsService->calculateGenericNumericTrend($athleteCalculatedMetrics->where('type', $metricType->value)->map(fn ($m) => (object) ['date' => $m->date, 'value' => $m->value]));
        $changePercentage = 'n/a';
        if (is_numeric($trendsData['averages']['Derniers 7 jours']) && is_numeric($trendsData['averages']['Derniers 30 jours']) && $trendsData['averages']['Derniers 30 jours'] != 0) {
            $change = (($trendsData['averages']['Derniers 7 jours'] - $trendsData['averages']['Derniers 30 jours']) / $trendsData['averages']['Derniers 30 jours']) * 100;
            $changePercentage = ($change > 0 ? '+' : '').number_format($change, 1).'%';
        }

        return [
            'label'                                 => $metricType->getLabelShort(),
            'latest_daily_value'                    => $latestDailyValue,
            'formatted_latest_daily_value'          => $this->formatMetricDisplayValue($latestDailyValue, $metricType),
            'latest_daily_value_date'               => $latestDailyValueDate,
            'is_latest_daily_value_today'           => $latestDailyValueDate ? $latestDailyValueDate->isSameDay($endDate) : false,
            'latest_weekly_average'                 => $latestWeeklyAverage,
            'formatted_latest_weekly_average'       => is_numeric($latestWeeklyAverage) ? number_format($latestWeeklyAverage, 1) : 'n/a',
            'latest_weekly_average_date'            => $latestWeeklyAverageDateKey ? Carbon::parse($latestWeeklyAverageDateKey) : null,
            'is_latest_weekly_average_current_week' => $latestWeeklyAverageDateKey ? Carbon::parse($latestWeeklyAverageDateKey)->isSameWeek($endDate) : false,
            'formatted_average_7_days'              => is_numeric($trendsData['averages']['Derniers 7 jours']) ? number_format($trendsData['averages']['Derniers 7 jours'], 1) : 'n/a',
            'formatted_average_30_days'             => is_numeric($trendsData['averages']['Derniers 30 jours']) ? number_format($trendsData['averages']['Derniers 30 jours'], 1) : 'n/a',
            'is_numerical'                          => true,
            'trend_icon'                            => $trend['trend'] !== 'n/a' ? $this->getTrendIcon($trend['trend']) : 'ellipsis-horizontal',
            'trend_color'                           => $trend['trend'] !== 'n/a' ? $this->determineTrendColor($trend['trend'], $metricType->getTrendOptimalDirection()) : 'zinc',
            'trend_percentage'                      => $changePercentage,
            'chart_data'                            => [
                'labels' => $weeklyData->keys()->map(fn ($d) => Carbon::parse($d)->addDays(6)->format('W Y'))->all(), // Labeling with end-of-week for chart
                'data'   => $weeklyData->values()->all(),
            ],
        ];
    }

    public function calculatePeriodStartDate(string $period, Carbon $endDate): Carbon
    {
        return match ($period) {
            'last_7_days'   => $endDate->copy()->subDays(7)->startOfDay(),
            'last_14_days'  => $endDate->copy()->subDays(14)->startOfDay(),
            'last_30_days'  => $endDate->copy()->subDays(30)->startOfDay(),
            'last_60_days'  => $endDate->copy()->subDays(60)->startOfDay(),
            'last_90_days'  => $endDate->copy()->subDays(90)->startOfDay(),
            'last_6_months' => $endDate->copy()->subMonths(6)->startOfDay(),
            'last_year'     => $endDate->copy()->subYear()->startOfDay(),
            'all_time'      => Carbon::createFromTimestamp(0),
            default         => $endDate->copy()->subDays(60)->startOfDay(),
        };
    }

    public function retrieveAthleteRawMetrics(Athlete $athlete, array $filters = []): Collection
    {
        $query = $athlete->metrics()->orderBy('date');
        $endDate = isset($filters['endDate']) ? Carbon::parse($filters['endDate']) : Carbon::now();

        if (isset($filters['metric_type']) && $filters['metric_type'] !== 'all') {
            if (MetricType::tryFrom($filters['metric_type'])) {
                $query->where('metric_type', $filters['metric_type']);
            }
        }

        if (isset($filters['period'])) {
            $this->applyMetricPeriodFilterToQuery($query, $filters['period'], $endDate);
        }

        return $query->get();
    }

    protected function applyMetricPeriodFilterToQuery($query, string $period, Carbon $endDate): void
    {
        switch ($period) {
            case 'last_7_days':
                $query->where('date', '>=', $endDate->copy()->subDays(7)->startOfDay());
                break;
            case 'last_14_days':
                $query->where('date', '>=', $endDate->copy()->subDays(14)->startOfDay());
                break;
            case 'last_30_days':
                $query->where('date', '>=', $endDate->copy()->subDays(30)->startOfDay());
                break;
            case 'last_60_days':
                $query->where('date', '>=', $endDate->copy()->subDays(60)->startOfDay());
                break;
            case 'last_90_days':
                $query->where('date', '>=', $endDate->copy()->subDays(90)->startOfDay());
                break;
            case 'last_6_months':
                $query->where('date', '>=', $endDate->copy()->subMonths(6)->startOfDay());
                break;
            case 'last_year':
                $query->where('date', '>=', $endDate->copy()->subYear()->startOfDay());
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

    public function getAthleteMetricDashboardSummary(Athlete $athlete, MetricType $metricType, string $period, ?Carbon $endDate = null): array
    {
        $endDate = $endDate ?? Carbon::now();
        $metricsForPeriod = $this->retrieveAthleteRawMetrics($athlete, ['metric_type' => $metricType->value, 'period' => $period, 'endDate' => $endDate]);

        // Directly call the now-aligned prepareSingleMetricDashboardDataFromCollection
        return $this->prepareSingleMetricDashboardDataFromCollection($metricsForPeriod, $metricType, $period, $endDate);
    }

    public function formatMetricDisplayValue(mixed $value, MetricType|CalculatedMetricType $metricType): string
    {
        if ($value === null) {
            return 'n/a';
        }

        if ($metricType instanceof MetricType) {
            if ($metricType->getValueColumn() === 'note') {
                return (string) $value;
            }

            $formattedValue = number_format($value, $metricType->getPrecision());
            $unit = $metricType->getUnit();
            $scale = $metricType->getScale();

            return $formattedValue.($unit ? ' '.$unit : '').($scale ? '/'.$scale : '');
        } elseif ($metricType instanceof CalculatedMetricType) {
            $formattedValue = number_format($value, $metricType->getPrecision());

            return $formattedValue;
        }

        return 'n/a';

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
