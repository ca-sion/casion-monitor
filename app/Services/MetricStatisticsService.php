<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Metric;
use App\Models\Athlete;
use Carbon\CarbonPeriod;
use App\Enums\MetricType;
use App\Models\TrainingPlanWeek;
use Illuminate\Support\Collection;

class MetricStatisticsService
{
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
    ];

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

            $metricsDataForDashboard['cih'] = $this->getDashboardWeeklyMetricDataForCollection($athlete, $athleteMetrics, 'cih', $period, $athletePlanWeeks);
            $metricsDataForDashboard['sbm'] = $this->getDashboardWeeklyMetricDataForCollection($athlete, $athleteMetrics, 'sbm', $period, $athletePlanWeeks);
            $metricsDataForDashboard['cph'] = $this->getDashboardWeeklyMetricDataForCollection($athlete, $athleteMetrics, 'cph', 'period', $athletePlanWeeks);
            $metricsDataForDashboard['cih_normalized'] = $this->getDashboardWeeklyMetricDataForCollection($athlete, $athleteMetrics, 'cih_normalized', $period, $athletePlanWeeks);
            $metricsDataForDashboard['ratio_cih_cph'] = $this->getDashboardWeeklyMetricDataForCollection($athlete, $athleteMetrics, 'ratio_cih_cph', $period, $athletePlanWeeks);
            $metricsDataForDashboard['ratio_cih_normalized_cph'] = $this->getDashboardWeeklyMetricDataForCollection($athlete, $athleteMetrics, 'ratio_cih_normalized_cph', $period, $athletePlanWeeks);

            $athlete->metricsDataForDashboard = $metricsDataForDashboard;

            $athlete->alerts = $this->getAthleteAlertsForCollection($athlete, $athleteMetrics, $period);
            $athlete->menstrualCycleInfo = $this->deduceMenstrualCyclePhaseForCollection($athlete, $athleteMetrics);
            $athlete->chargeAlerts = $this->getChargeAlertsForCollection($athlete, $athleteMetrics, $athletePlanWeeks, now()->startOfWeek(Carbon::MONDAY));
            $athlete->readinessStatus = $this->getAthleteReadinessStatus($athlete, $athleteMetrics);

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
            $trends = $this->getMetricTrendsForCollection($metricsForPeriod, $metricType);
            $metricData['average_7_days'] = $trends['averages']['Derniers 7 jours'] ?? null;
            $metricData['average_30_days'] = $trends['averages']['Derniers 30 jours'] ?? null;
            $metricData['formatted_average_7_days'] = $this->formatMetricValue($metricData['average_7_days'], $metricType);
            $metricData['formatted_average_30_days'] = $this->formatMetricValue($metricData['average_30_days'], $metricType);

            $evolutionTrendData = $this->getEvolutionTrendForCollection($metricsForPeriod, $metricType);
            if ($metricData['average_7_days'] !== null && $evolutionTrendData['trend'] !== 'N/A') {
                $metricData['trend_icon'] = match ($evolutionTrendData['trend']) {
                    'increasing' => 'arrow-trending-up',
                    'decreasing' => 'arrow-trending-down',
                    'stable'     => 'minus',
                    default      => 'ellipsis-horizontal',
                };
                $metricData['trend_color'] = match ($evolutionTrendData['trend']) {
                    'increasing' => 'lime',
                    'decreasing' => 'rose',
                    default      => 'zinc',
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
    protected function getDashboardWeeklyMetricDataForCollection(Athlete $athlete, Collection $allAthleteMetrics, string $metricKey, string $period, Collection $athletePlanWeeks): array
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

            $cih = $metricsForWeek->where('metric_type', MetricType::POST_SESSION_SESSION_LOAD->value)->sum('value');

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
                    $sbmValue = $this->calculateSbmForCollection($sbmMetricsForWeek->get($dateStr));
                    if ($sbmValue > 0) {
                        $sbmSum += $sbmValue;
                        $sbmCount++;
                    }
                }
            }
            $sbm = $sbmCount > 0 ? $sbmSum / $sbmCount : null;

            $planWeek = $athletePlanWeeks->firstWhere('start_date', $weekStartDate->toDateString());
            $cph = $planWeek ? $this->calculateCph($planWeek) : 0.0;

            $cihNormalized = $this->calculateCihNormalized($athlete, $weekStartDate);
            $ratioCihCph = ($cih > 0 && $cph > 0) ? $cih / $cph : null;
            $ratioCihNormalizedCph = ($cihNormalized > 0 && $cph > 0) ? $cihNormalized / $cph : null;

            $value = match ($metricKey) {
                'cih'                      => $cih,
                'sbm'                      => $sbm,
                'cph'                      => $cph,
                'cih_normalized'           => $cihNormalized,
                'ratio_cih_cph'            => $ratioCihCph,
                'ratio_cih_normalized_cph' => $ratioCihNormalizedCph,
                default                    => null,
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

        $trend = $this->calculateTrendFromNumericCollection($allWeeklyData);
        $changePercentage = 'N/A';
        if (is_numeric($average7Days) && is_numeric($average30Days) && $average30Days != 0) {
            $change = (($average7Days - $average30Days) / $average30Days) * 100;
            $changePercentage = ($change > 0 ? '+' : '').number_format($change, 1).'%';
        }

        // Prépare les données pour le retour
        return [
            'label'                     => $metricKey,
            'formatted_last_value'      => is_numeric($lastValue) ? number_format($lastValue, 1) : 'N/A',
            'formatted_average_7_days'  => is_numeric($average7Days) ? number_format($average7Days, 1) : 'N/A',
            'formatted_average_30_days' => is_numeric($average30Days) ? number_format($average30Days, 1) : 'N/A',
            'is_numerical'              => true,
            'trend_icon'                => $trend['trend'] !== 'N/A' ? match ($trend['trend']) {
                'increasing' => 'arrow-trending-up', 'decreasing' => 'arrow-trending-down', default => 'minus'
            } : 'ellipsis-horizontal',
            'trend_color' => $trend['trend'] !== 'N/A' ? match ($trend['trend']) {
                'increasing' => 'lime', 'decreasing' => 'rose', default => 'zinc'
            } : 'zinc',
            'trend_percentage' => $changePercentage,
            'chart_data'       => [
                'labels' => $allWeeklyData->pluck('date')->map(fn ($d) => $d->format('W Y'))->all(),
                'data'   => $allWeeklyData->pluck('value')->all(),
            ],
        ];
    }

    /**
     * Récupère les alertes pour un athlète à partir d'une collection de métriques pré-chargée.
     */
    protected function getAthleteAlertsForCollection(Athlete $athlete, Collection $metrics, string $period = 'last_60_days'): array
    {
        return $this->getAthleteAlerts($athlete, $period, $metrics);
    }

    /**
     * Déduit la phase du cycle menstruel d'un athlète féminin à partir d'une collection de métriques pré-chargée.
     */
    protected function deduceMenstrualCyclePhaseForCollection(Athlete $athlete, Collection $allMetrics): array
    {
        return $this->deduceMenstrualCyclePhase($athlete, $allMetrics);
    }

    /**
     * Récupère les alertes de charge pour un athlète à partir de collections pré-chargées.
     */
    protected function getChargeAlertsForCollection(Athlete $athlete, Collection $allMetrics, Collection $planWeeks, Carbon $weekStartDate): array
    {
        return $this->getChargeAlerts($athlete, $weekStartDate, $allMetrics, $planWeeks);
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
            return null; // Toutes les métriques sont manquantes
        }

        return (float) (($sbmSum / $maxPossibleSbm) * 10);
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
     * Calcule la moyenne des métriques sur différentes périodes pour un athlète.
     */
    public function getMetricTrends(Athlete $athlete, MetricType $metricType): array
    {
        if ($metricType->getValueColumn() === 'note') {
            return [
                'metric_label' => $metricType->getLabel(),
                'unit'         => $metricType->getUnit(),
                'averages'     => [],
                'reason'       => 'La métrique n\'est pas numérique et ne peut pas être moyennée.',
            ];
        }

        $query = $athlete->metrics()
            ->where('metric_type', $metricType->value)
            ->orderBy('date', 'asc');

        $metrics = $query->get();

        return $this->getMetricTrendsForCollection($metrics, $metricType);
    }

    /**
     * Calcule les tendances des métriques sur différentes périodes à partir d'une collection déjà filtrée.
     *
     * @param  Collection<Metric>  $metrics  La collection de métriques à analyser.
     */
    public function getMetricTrendsForCollection(Collection $metrics, MetricType $metricType): array
    {
        if ($metricType->getValueColumn() === 'note') {
            return [
                'metric_label' => $metricType->getLabel(),
                'unit'         => $metricType->getUnit(),
                'averages'     => [],
                'reason'       => 'La métrique n\'est pas numérique et ne peut pas être moyennée.',
            ];
        }

        $trends = [];
        $valueColumn = $metricType->getValueColumn();
        $periods = [
            'Derniers 7 jours'  => 7,
            'Derniers 14 jours' => 14,
            'Derniers 30 jours' => 30,
            'Derniers 90 jours' => 90,
            'Derniers 6 mois'   => 180,
            'Derniers 1 an'     => 365,
        ];
        $now = Carbon::now();

        foreach ($periods as $label => $days) {
            $startDate = $now->copy()->subDays($days)->startOfDay();
            $average = $metrics->filter(fn ($m) => $m->date && $m->date->greaterThanOrEqualTo($startDate) && is_numeric($m->{$valueColumn}))->avg($valueColumn);
            $trends[$label] = $average;
        }

        $allTimeAverage = $metrics->filter(fn ($m) => is_numeric($m->{$valueColumn}))->avg($valueColumn);
        $trends['Total'] = $allTimeAverage;

        return [
            'metric_label' => $metricType->getLabel(),
            'unit'         => $metricType->getUnit(),
            'averages'     => $trends,
        ];
    }

    /**
     * Calcule la tendance d'évolution (accroissement/décroissement) pour une métrique sur une période.
     * Compare la valeur moyenne au début et à la fin de la période ou la première/dernière valeur.
     *
     * @param  Collection<Metric>  $metrics  Collection de métriques déjà filtrée par type.
     * @return array ['trend' => 'increasing'|'decreasing'|'stable'|'N/A', 'change' => float|null, 'reason' => string|null]
     */
    public function getEvolutionTrendForCollection(Collection $metrics, MetricType $metricType): array
    {
        if ($metricType->getValueColumn() === 'note') {
            return ['trend' => 'N/A', 'change' => null, 'reason' => 'La métrique n\'est pas numérique.'];
        }

        $valueColumn = $metricType->getValueColumn();
        $numericMetrics = $metrics->filter(fn ($m) => is_numeric($m->{$valueColumn}))->sortBy('date');

        if ($numericMetrics->count() < 2) {
            return ['trend' => 'N/A', 'change' => null, 'reason' => 'Pas assez de données pour calculer une tendance.'];
        }

        $totalCount = $numericMetrics->count();
        $segmentSize = floor($totalCount / 3); // Utilise le premier et le dernier tiers des points de données pour une tendance plus lisse.

        if ($segmentSize === 0) {
            // Si moins de 3 points de données, compare la première et la dernière valeur.
            $firstValue = $numericMetrics->first()->{$valueColumn};
            $lastValue = $numericMetrics->last()->{$valueColumn};
        } else {
            $firstSegment = $numericMetrics->take($segmentSize);
            $lastSegment = $numericMetrics->slice($totalCount - $segmentSize);

            $firstValue = $firstSegment->avg($valueColumn);
            $lastValue = $lastSegment->avg($valueColumn);
        }

        if ($firstValue === null || $lastValue === null) {
            return ['trend' => 'N/A', 'change' => null, 'reason' => 'Impossible de calculer la tendance avec les valeurs fournies.'];
        }

        if ($firstValue == 0 && $lastValue != 0) {
            $change = 100; // Augmentation significative à partir de zéro.
        } elseif ($firstValue == 0 && $lastValue == 0) {
            $change = 0; // Aucun changement si les deux sont zéro.
        } else {
            $change = (($lastValue - $firstValue) / $firstValue) * 100;
        }

        $trend = 'stable';
        // Définit un petit seuil pour considérer la tendance comme stable, afin d'éviter les micro-fluctuations.
        if ($change > 0.5) {
            $trend = 'increasing';
        } elseif ($change < -0.5) {
            $trend = 'decreasing';
        }

        return [
            'trend'  => $trend,
            'change' => $change,
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
            $trends = $this->getMetricTrendsForCollection($metricsForPeriod, $metricType);

            $metricData['average_7_days'] = $trends['averages']['Derniers 7 jours'] ?? null;
            $metricData['average_30_days'] = $trends['averages']['Derniers 30 jours'] ?? null;

            $metricData['formatted_average_7_days'] = $this->formatMetricValue($metricData['average_7_days'], $metricType);
            $metricData['formatted_average_30_days'] = $this->formatMetricValue($metricData['average_30_days'], $metricType);

            $evolutionTrendData = $this->getEvolutionTrendForCollection($metricsForPeriod, $metricType);

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
     * Calcule la Charge Subjective Réelle par Séance (CSR-S) pour une métrique de RPE donnée.
     *
     * @param  Metric  $rpeMetric  La métrique de RPE (Rate of Perceived Exertion).
     * @return float La Charge Subjective Réelle par Séance (CSR-S).
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
     *
     * @param  Athlete  $athlete  L'athlète concerné.
     * @param  Carbon  $weekStartDate  La date de début de la semaine.
     * @return float La Charge Interne Hebdomadaire (CIH).
     */
    public function calculateCih(Athlete $athlete, Carbon $weekStartDate): float
    {
        $weekEndDate = $weekStartDate->copy()->endOfWeek(Carbon::SUNDAY);

        $rpeMetrics = $athlete->metrics()
            ->where('metric_type', MetricType::POST_SESSION_SESSION_LOAD->value)
            ->whereBetween('date', [$weekStartDate->toDateString(), $weekEndDate->toDateString()])
            ->get();

        $cih = $rpeMetrics->sum('value');

        return (float) $cih;
    }

    /**
     * Calcule la Charge Interne Hebdomadaire Normalisée (CIH_NORMALIZED) pour une semaine donnée et un athlète.
     * CIH_NORMALIZED est calculée comme : (Somme des POST_SESSION_SESSION_LOAD / Nombre de jours avec POST_SESSION_SESSION_LOAD) * 4.
     *
     * @param  Athlete  $athlete  L'athlète concerné.
     * @param  Carbon  $weekStartDate  La date de début de la semaine.
     * @return float La Charge Interne Hebdomadaire Normalisée (CIH_NORMALIZED).
     */
    public function calculateCihNormalized(Athlete $athlete, Carbon $weekStartDate): float
    {
        $weekEndDate = $weekStartDate->copy()->endOfWeek(Carbon::SUNDAY);

        $rpeMetrics = $athlete->metrics()
            ->where('metric_type', MetricType::POST_SESSION_SESSION_LOAD->value)
            ->whereBetween('date', [$weekStartDate->toDateString(), $weekEndDate->toDateString()])
            ->get();

        if ($rpeMetrics->isEmpty()) {
            return 0.0;
        }

        $sumRpe = $rpeMetrics->sum('value');
        $distinctDays = $rpeMetrics->pluck('date')->unique()->count();

        if ($distinctDays === 0) {
            return 0.0;
        }

        $averageSessionLoad = $sumRpe / $distinctDays;

        $normalizationDays = self::ALERT_THRESHOLDS['CIH_NORMALIZED']['normalization_days'];

        return (float) ($averageSessionLoad * $normalizationDays);
    }

    /**
     * Calcule le Score de Bien-être Matinal (SBM) pour un jour donné et un athlète.
     * SBM est calculé comme suit : MORNING_SLEEP_QUALITY + (10 - MORNING_GENERAL_FATIGUE) + (10 - MORNING_PAIN) + MORNING_MOOD_WELLBEING.
     *
     * @param  Athlete  $athlete  L'athlète concerné.
     * @param  Carbon  $date  La date pour laquelle calculer le SBM.
     * @return float Le Score de Bien-être Matinal (SBM).
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
            return null; // Toutes les métriques sont manquantes
        }

        return (float) (($sbmSum / $maxPossibleSbm) * 10);
    }

    /**
     * Calcule la Charge Planifiée Hebdomadaire (CPH) pour une semaine donnée.
     * CPH est calculée comme : volume_planned * (intensity_planned / 10).
     *
     * @param  TrainingPlanWeek  $planWeek  La semaine du plan d'entraînement.
     * @return float La Charge Planifiée Hebdomadaire (CPH).
     */
    public function calculateCph(TrainingPlanWeek $planWeek): float
    {
        $volumePlanned = $planWeek->volume_planned ?? 0;
        $intensityPlanned = $planWeek->intensity_planned ?? 0;
        $normalizedIntensity = $intensityPlanned / 10;

        $cph = $volumePlanned * $normalizedIntensity;

        return (float) $cph;
    }

    /**
     * Calcule le Ratio CIH / CPH.
     *
     * @param  float  $cih  Charge Interne Hebdomadaire.
     * @param  float  $cph  Charge Planifiée Hebdomadaire.
     * @return float Le ratio CIH/CPH. Retourne 0.0 si CPH est zéro pour éviter une division par zéro.
     */
    public function calculateRatio(float $cih, float $cph): float
    {
        if ($cph === 0.0) {
            return 0.0;
        }

        return $cih / $cph;
    }

    // Dans votre service MetricStatisticsService
    public function calculateOverallReadinessScore(Athlete $athlete, Collection $allMetrics): int
    {
        $readinessScore = 100;
        $today = Carbon::now()->startOfDay();
        $sevenDaysAgo = Carbon::now()->subDays(7)->startOfDay();

        // 1. Impact du SBM (sur 10)
        $sbmMetrics = $allMetrics->whereIn('metric_type', [
            MetricType::MORNING_SLEEP_QUALITY,
            MetricType::MORNING_GENERAL_FATIGUE,
            MetricType::MORNING_PAIN,
            MetricType::MORNING_MOOD_WELLBEING,
        ])->filter(fn ($m) => $m->date->isToday()); // On prend les métriques du jour

        // Calcul du SBM via votre méthode existante (assumée retourner sur 0-10)
        $dailySbm = $this->calculateSbmForCollection($sbmMetrics);

        if ($dailySbm !== null) {
            // Impact du SBM : Plus le SBM est bas, plus le score de readiness diminue.
            // Convertissons le SBM sur 10 en un impact sur 100 points de readiness.
            // Si SBM = 10 (parfait), 0 pénalité. Si SBM = 0, pénalité maximale.
            // Ex: (10 - SBM) * 5. Si SBM=10, (10-10)*5=0. Si SBM=0, (10-0)*5=50.
            $readinessScore -= (10 - $dailySbm) * 5;
        }

        // 2. Impact de la VFC (HRV)
        $hrvMetrics = $allMetrics->where('metric_type', MetricType::MORNING_HRV->value)->sortByDesc('date');
        $lastHrv = $hrvMetrics->first()?->value;

        if ($lastHrv !== null) {
            // Calcule la moyenne HRV des 7 derniers jours (hors aujourd'hui) pour la baseline
            $hrv7DayAvg = $hrvMetrics->where('date', '>=', now()->subDays(7)->startOfDay())
                ->where('date', '<', now()->startOfDay())
                ->avg('value');

            if ($hrv7DayAvg && $hrv7DayAvg > 0) {
                $changePercent = (($lastHrv - $hrv7DayAvg) / $hrv7DayAvg) * 100;

                if ($changePercent < -10) { // Chute de plus de 10%
                    $readinessScore -= 20;
                } elseif ($changePercent < -5) { // Chute de 5% à 10%
                    $readinessScore -= 10;
                }
                // Aucune pénalité si stable ou en hausse.
            }
        }

        // 3. Impact de la Douleur (MORNING_PAIN)
        $morningPain = $allMetrics->where('metric_type', MetricType::MORNING_PAIN->value)
            ->where('date', now()->startOfDay())
            ->first()?->value;

        if ($morningPain !== null && $morningPain > 0) {
            // Pénalité plus forte pour la douleur
            $readinessScore -= ($morningPain * 4); // Ex: 4 points par niveau de douleur sur 10
        }

        // 4. Impact des métriques pré-session (remplies juste avant la session)
        $preSessionEnergy = $allMetrics->where('metric_type', MetricType::PRE_SESSION_ENERGY_LEVEL->value)
            ->where('date', now()->startOfDay())
            ->first()?->value;

        if ($preSessionEnergy !== null && $preSessionEnergy <= 4) { // Si l'énergie est très basse (1-4)
            $readinessScore -= 15;
        } elseif ($preSessionEnergy !== null && $preSessionEnergy <= 6) { // Si l'énergie est moyenne (5-6)
            $readinessScore -= 5;
        }

        $preSessionLegFeel = $allMetrics->where('metric_type', MetricType::PRE_SESSION_LEG_FEEL->value)
            ->where('date', now()->startOfDay())
            ->first()?->value;

        if ($preSessionLegFeel !== null && $preSessionLegFeel <= 4) { // Si les jambes sont très lourdes (1-4)
            $readinessScore -= 15;
        } elseif ($preSessionLegFeel !== null && $preSessionLegFeel <= 6) { // Si les jambes sont moyennes (5-6)
            $readinessScore -= 5;
        }

        // 5. Impact du ratio de charge (CIH/CPH) - à évaluer sur la semaine en cours
        $currentWeekStartDate = now()->startOfWeek(Carbon::MONDAY);
        // Assurez-vous que athlete->currentTrainingPlan existe et que trainingPlanWeeks est une collection
        $trainingPlanWeeks = $athlete->currentTrainingPlan?->weeks ?? collect();

        $cihData = $this->getDashboardWeeklyMetricDataForCollection($athlete, $allMetrics, 'cih', 'last_7_days', $trainingPlanWeeks);
        $cphData = $this->getDashboardWeeklyMetricDataForCollection($athlete, $allMetrics, 'cph', 'last_7_days', $trainingPlanWeeks);

        // Récupérer la dernière valeur calculée pour la semaine en cours
        $currentCih = end($cihData['chart_data']['data']) ?? 0;
        $currentCph = end($cphData['chart_data']['data']) ?? 0;

        if ($currentCih > 0 && $currentCph > 0) {
            $ratio = $currentCih / $currentCph;
            $overloadThreshold = self::ALERT_THRESHOLDS['CHARGE_LOAD']['ratio_overload_threshold'] ?? 1.3;

            if ($ratio > $overloadThreshold) { // Surcharge
                $readinessScore -= 15 * ($ratio - $overloadThreshold); // Pénalité croissante
            }
            // Pas de pénalité directe pour la sous-charge sur la readiness immédiate ici.
        }

        // Assurez-vous que le score reste entre 0 et 100
        return max(0, min(100, (int) round($readinessScore)));
    }

    // Dans votre service MetricStatisticsService
    public function getAthleteReadinessStatus(Athlete $athlete, Collection $allMetrics): array
    {
        // Calcul du score global de readiness
        $readinessScore = $this->calculateOverallReadinessScore($athlete, $allMetrics);

        $status = [
            'level'           => 'green', // Vert par défaut
            'message'         => "L'athlète est prêt pour l'entraînement !",
            'readiness_score' => $readinessScore,
            'recommendation'  => "Poursuivre l'entraînement planifié.",
            'alerts'          => [], // Sera rempli par getAthleteAlerts
        ];

        // Récupération des alertes détaillées
        $alerts = $this->getAthleteAlertsForCollection($athlete, $allMetrics);
        $status['alerts'] = $alerts;

        // Définition des règles pour les niveaux de readiness basées sur le score
        if ($readinessScore < 50) {
            $status['level'] = 'red';
            $status['message'] = 'Faible readiness. Risque accru de fatigue ou blessure.';
            $status['recommendation'] = 'Repos complet, récupération active très légère, ou réévaluation du plan. Ne pas forcer un entraînement intense.';
        } elseif ($readinessScore < 70) {
            $status['level'] = 'orange';
            $status['message'] = 'Readiness modérée. Signes de fatigue ou de stress.';
            $status['recommendation'] = "Adapter l'entraînement : réduire le volume/l'intensité, se concentrer sur la technique, ou privilégier la récupération.";
        } elseif ($readinessScore < 85) {
            $status['level'] = 'yellow';
            $status['message'] = 'Bonne readiness, quelques points à surveiller.';
            $status['recommendation'] = 'Entraînement normal, mais rester attentif(ve) aux sensations et adapter si nécessaire.';
        }
        // Si >= 85, reste 'green' par défaut

        // Règle d'exception pour la douleur sévère, qui peut surclasser le score
        $morningPain = $allMetrics->where('metric_type', MetricType::MORNING_PAIN->value)
            ->where('date', now()->startOfDay())
            ->first()?->value;
        if ($morningPain !== null && $morningPain >= 7) { // Douleur de 7/10 ou plus
            $status['level'] = 'red';
            $status['message'] = 'Douleur sévère signalée. Repos ou consultation médicale.';
            $status['recommendation'] = "Absolument aucun entraînement intense. Focalisation sur la récupération et l'identification de la cause de la douleur.";
        }

        // Règle d'exception pour le premier jour des règles avec niveau d'énergie bas
        $firstDayPeriod = $allMetrics->where('metric_type', MetricType::MORNING_FIRST_DAY_PERIOD->value)
            ->where('date', now()->startOfDay())
            ->first()?->value;
        $preSessionEnergy = $allMetrics->where('metric_type', MetricType::PRE_SESSION_ENERGY_LEVEL->value)
            ->where('date', now()->startOfDay())
            ->first()?->value;

        if ($firstDayPeriod && ($preSessionEnergy !== null && $preSessionEnergy <= 4)) {
            // Si l'état n'est pas déjà rouge, on le passe à orange
            if ($status['level'] !== 'red') {
                $status['level'] = 'orange';
                $status['message'] = "Premier jour des règles avec niveau d'énergie bas.";
                $status['recommendation'] = "Adapter l'entraînement aux sensations, privilégier des activités plus douces ou de la récupération active.";
            }
        }

        return $status;
    }

    /**
     * Récupère un résumé des métriques hebdomadaires (CIH, SBM, CPH, Ratio CIH/CPH) pour un athlète.
     *
     * @return array ['cih' => float, 'sbm' => float|string, 'cph' => float, 'ratio_cih_cph' => float|string]
     */
    public function getAthleteWeeklyMetricsSummary(Athlete $athlete, Carbon $weekStartDate): array
    {
        $cih = $this->calculateCih($athlete, $weekStartDate);

        $sbmSum = 0;
        $sbmCount = 0;
        $periodCarbon = \Carbon\CarbonPeriod::create($weekStartDate, '1 day', $weekStartDate->copy()->endOfWeek(Carbon::SUNDAY));
        foreach ($periodCarbon as $date) {
            $sbmValue = $this->calculateSbm($athlete, $date);
            if ($sbmValue > 0) {
                $sbmSum += $sbmValue;
                $sbmCount++;
            }
        }
        $sbm = $sbmCount > 0 ? round($sbmSum / $sbmCount, 1) : 'N/A';

        $trainingPlanWeek = $this->getTrainingPlanWeekForAthlete($athlete, $weekStartDate);
        $cph = $trainingPlanWeek ? $this->calculateCph($trainingPlanWeek) : 0.0;

        $ratioCihCph = ($cih > 0 && $cph > 0) ? round($this->calculateRatio($cih, $cph), 2) : 'N/A';

        $cihNormalized = $this->calculateCihNormalized($athlete, $weekStartDate);
        $ratioCihNormalizedCph = ($cihNormalized > 0 && $cph > 0) ? round($this->calculateRatio($cihNormalized, $cph), 2) : 'N/A';

        return [
            'cih'                      => $cih,
            'sbm'                      => $sbm,
            'cph'                      => $cph,
            'cih_normalized'           => $cihNormalized,
            'ratio_cih_cph'            => $ratioCihCph,
            'ratio_cih_normalized_cph' => $ratioCihNormalizedCph,
        ];
    }

    /**
     * Prépare les données de graphique pour les métriques hebdomadaires (CIH, SBM, CPH, Ratio CIH/CPH) sur une période donnée.
     *
     * @param  Athlete  $athlete  L'athlète concerné.
     * @param  string  $period  La période pour laquelle récupérer les données (ex: 'last_60_days').
     * @param  string  $metricKey  La clé de la métrique hebdomadaire (ex: 'cih', 'sbm').
     * @return array Les données formatées pour un graphique.
     */
    public function getWeeklyMetricsChartData(Athlete $athlete, string $period, string $metricKey): array
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
            $summary = $this->getAthleteWeeklyMetricsSummary($athlete, $currentWeek);
            $weekLabel = $currentWeek->format('W Y');
            $value = $summary[$metricKey];

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
            'label'           => match ($metricKey) {
                'cih'                      => 'CIH',
                'sbm'                      => 'SBM',
                'cph'                      => 'CPH',
                'cih_normalized'           => 'CIH Normalisée',
                'ratio_cih_cph'            => 'Ratio CIH/CPH',
                'ratio_cih_normalized_cph' => 'Ratio CIH Normalisée/CPH',
                default                    => '',
            },
        ];
    }

    /**
     * Prépare toutes les données agrégées pour le tableau de bord d'une métrique hebdomadaire spécifique.
     *
     * @param  Athlete  $athlete  L'athlète concerné.
     * @param  string  $metricKey  La clé de la métrique hebdomadaire (ex: 'cih', 'sbm').
     * @param  string  $period  La période pour laquelle récupérer les données.
     * @return array Les données formatées pour le tableau de bord.
     */
    public function getDashboardWeeklyMetricData(Athlete $athlete, string $metricKey, string $period): array
    {
        $now = Carbon::now();
        $currentWeekStartDate = $now->startOfWeek(Carbon::MONDAY);

        $metricData = [
            'label' => match ($metricKey) {
                'cih'                      => 'Charge Interne Hebdomadaire',
                'sbm'                      => 'Score de Bien-être Matinal',
                'cph'                      => 'Charge Planifiée Hebdomadaire',
                'cih_normalized'           => 'Charge Interne Hebdomadaire Normalisée',
                'ratio_cih_cph'            => 'Ratio CIH/CPH',
                'ratio_cih_normalized_cph' => 'Ratio CIH Normalisée/CPH',
                default                    => '',
            },
            'short_label' => match ($metricKey) {
                'cih'                      => 'CIH',
                'sbm'                      => 'SBM',
                'cph'                      => 'CPH',
                'cih_normalized'           => 'CIH Normalisée',
                'ratio_cih_cph'            => 'Ratio CIH/CPH',
                'ratio_cih_normalized_cph' => 'Ratio CIH Normalisée/CPH',
                default                    => '',
            },
            'description' => match ($metricKey) {
                'cih'                      => 'Somme des Charges Subjectives Réelles par Séance (CSR-S) pour la semaine.',
                'sbm'                      => 'Score agrégé des métriques de bien-être matinal (Qualité du sommeil, Fatigue générale, Douleur, Humeur) sur une échelle de 0 à 10.',
                'cph'                      => 'Charge d\'entraînement planifiée pour la semaine, basée sur le volume et l\'intensité.',
                'cih_normalized'           => 'Charge interne hebdomadaire normalisée pour la comparabilité avec la CPH.',
                'ratio_cih_cph'            => 'Ratio entre la Charge Interne Hebdomadaire (CIH) et la Charge Planifiée Hebdomadaire (CPH).',
                'ratio_cih_normalized_cph' => 'Ratio entre la Charge Interne Hebdomadaire Normalisée (CIH Normalisée) et la Charge Planifiée Hebdomadaire (CPH).',
                default                    => '',
            },
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

        $weeklySummary = $this->getAthleteWeeklyMetricsSummary($athlete, $currentWeekStartDate);
        $currentValue = $weeklySummary[$metricKey];
        $metricData['last_value'] = $currentValue;
        $metricData['formatted_last_value'] = is_numeric($currentValue) ? number_format($currentValue, 1) : 'N/A';

        $metricData['chart_data'] = $this->getWeeklyMetricsChartData($athlete, $period, $metricKey);

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
            $summary = $this->getAthleteWeeklyMetricsSummary($athlete, $tempWeek);
            if (is_numeric($summary[$metricKey])) {
                $allWeeklyData->push((object) ['date' => $tempWeek->copy(), 'value' => $summary[$metricKey]]);
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

        $evolutionTrendData = $this->calculateTrendFromNumericCollection($allWeeklyData);

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
     * Détecte les alertes liées à la charge (CIH/CPH) et au bien-être (SBM) pour une semaine donnée.
     *
     * @param  Athlete  $athlete  L'athlète concerné.
     * @param  Carbon  $weekStartDate  La date de début de la semaine.
     * @param  Collection|null  $allMetrics  Collection de toutes les métriques de l'athlète (optionnel, pour optimisation).
     * @param  Collection|null  $planWeeks  Collection des semaines de plan d'entraînement (optionnel, pour optimisation).
     * @return array Un tableau d'alertes.
     */
    public function getChargeAlerts(Athlete $athlete, Carbon $weekStartDate, ?Collection $allMetrics = null, ?Collection $planWeeks = null): array
    {
        $alerts = [];

        if (is_null($planWeeks)) {
            $trainingPlanWeek = $this->getTrainingPlanWeekForAthlete($athlete, $weekStartDate);
        } else {
            $assignedPlan = $athlete->currentTrainingPlan;
            $trainingPlanWeek = $planWeeks->firstWhere('start_date', $weekStartDate->toDateString());
        }

        $alerts = array_merge($alerts, $this->analyzeChargeMetrics($athlete, $weekStartDate, $trainingPlanWeek, $allMetrics));

        $alerts = array_merge($alerts, $this->analyzeSbmMetrics($athlete, $weekStartDate, $allMetrics));

        $alerts = array_merge($alerts, $this->analyzeMultiWeekTrends($athlete, $weekStartDate, $allMetrics));

        return $alerts;
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
        $cihNormalized = $this->calculateCihNormalized($athlete, $weekStartDate);
        $cph = $trainingPlanWeek ? $this->calculateCph($trainingPlanWeek) : 0.0;

        $chargeThresholds = self::ALERT_THRESHOLDS['CHARGE_LOAD'];

        if ($cihNormalized > 0 && $cph > 0) {
            $ratio = $this->calculateRatio($cihNormalized, $cph);

            if ($ratio < $chargeThresholds['ratio_underload_threshold']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Sous-charge potentielle : Charge réelle normalisée ('.number_format($cihNormalized, 1).") significativement inférieure au plan ({$cph}). Ratio: ".number_format($ratio, 2).'.'];
            } elseif ($ratio > $chargeThresholds['ratio_overload_threshold']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Surcharge potentielle : Charge réelle normalisée ('.number_format($cihNormalized, 1).") significativement supérieure au plan ({$cph}). Ratio: ".number_format($ratio, 2).'.'];
            } else {
                $alerts[] = ['type' => 'success', 'message' => 'Charge réelle normalisée ('.number_format($cihNormalized, 1).") en adéquation avec le plan ({$cph}). Ratio: ".number_format($ratio, 2).'.'];
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
                $sbmValue = $this->calculateSbm($athlete, $date);
            } else {
                $dailyMetrics = $allMetrics->filter(fn ($m) => $m->date->format('Y-m-d') === $date->format('Y-m-d'));
                $sbmValue = $this->calculateSbmForCollection($dailyMetrics);
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
                $sbmValue = $this->calculateSbm($athlete, $currentDate);
            } else {
                $dailyMetrics = $allMetrics->filter(fn ($m) => $m->date->format('Y-m-d') === $currentDate->format('Y-m-d'));
                $sbmValue = $this->calculateSbmForCollection($dailyMetrics);
            }

            if ($sbmValue !== null && $sbmValue !== 0.0) {
                $sbmDataCollection->push((object) ['date' => $currentDate->copy(), 'value' => $sbmValue]);
            }
            $currentDate->addDay();
        }

        if ($sbmDataCollection->count() > 5) {
            $sbmThresholds = self::ALERT_THRESHOLDS['SBM'];
            $sbmTrend = $this->calculateTrendFromNumericCollection($sbmDataCollection);
            if ($sbmTrend['trend'] === 'decreasing' && $sbmTrend['change'] < $sbmThresholds['trend_decrease_percent']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Baisse significative du SBM ('.number_format($sbmTrend['change'], 1).'%) sur les 30 derniers jours.'];
            }
        }

        if (is_null($allMetrics)) {
            $hrvMetrics = $this->getAthleteMetrics($athlete, ['metric_type' => MetricType::MORNING_HRV->value, 'period' => $period]);
        } else {
            $hrvMetrics = $allMetrics->filter(fn ($m) => $m->metric_type === MetricType::MORNING_HRV);
        }

        if ($hrvMetrics->count() > 5) {
            $hrvTrend = $this->getEvolutionTrendForCollection($hrvMetrics, MetricType::MORNING_HRV);
            $hrvThresholds = self::ALERT_THRESHOLDS[MetricType::MORNING_HRV->value];
            if ($hrvTrend['trend'] === 'decreasing' && $hrvTrend['change'] < $hrvThresholds['trend_decrease_percent']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Diminution significative de la VFC ('.number_format($hrvTrend['change'], 1).'%) sur les 30 derniers jours.'];
            }
        }

        return $alerts;
    }

    /**
     * Calcule la tendance d'évolution pour une collection de valeurs numériques et de dates.
     * Cette méthode est utilisée pour les métriques synthétiques comme le SBM qui ne sont pas directement des "MetricType".
     *
     * @param  Collection<object|array>  $dataCollection  Collection d'objets/tableaux avec 'date' et 'value'.
     * @return array ['trend' => 'increasing'|'decreasing'|'stable'|'N/A', 'change' => float|null, 'reason' => string|null]
     */
    public function calculateTrendFromNumericCollection(Collection $dataCollection): array
    {
        $numericData = $dataCollection->filter(fn ($item) => is_numeric($item->value ?? $item['value'] ?? null))
            ->sortBy(fn ($item) => $item->date ?? $item['date']);

        if ($numericData->count() < 2) {
            return ['trend' => 'N/A', 'change' => null, 'reason' => 'Pas assez de données pour calculer une tendance.'];
        }

        $totalCount = $numericData->count();
        $segmentSize = floor($totalCount / 3);

        if ($segmentSize === 0) {
            $firstValue = $numericData->first()->value ?? $numericData->first()['value'];
            $lastValue = $numericData->last()->value ?? $numericData->last()['value'];
        } else {
            $firstSegment = $numericData->take($segmentSize);
            $lastSegment = $numericData->slice($totalCount - $segmentSize);

            $firstValue = $firstSegment->avg(fn ($item) => $item->value ?? $item['value']);
            $lastValue = $lastSegment->avg(fn ($item) => $item->value ?? $item['value']);
        }

        if ($firstValue === null || $lastValue === null) {
            return ['trend' => 'N/A', 'change' => null, 'reason' => 'Impossible de calculer la tendance avec les valeurs fournies.'];
        }

        if ($firstValue == 0 && $lastValue != 0) {
            $change = 100;
        } elseif ($firstValue == 0 && $lastValue == 0) {
            $change = 0;
        } else {
            $change = (($lastValue - $firstValue) / $firstValue) * 100;
        }

        $trend = 'stable';
        if ($change > 0.5) {
            $trend = 'increasing';
        } elseif ($change < -0.5) {
            $trend = 'decreasing';
        }

        return [
            'trend'  => $trend,
            'change' => $change,
        ];
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
            $averageFatigue7Days = $this->getMetricTrendsForCollection($fatigueMetrics, MetricType::MORNING_GENERAL_FATIGUE)['averages']['Derniers 7 jours'] ?? null;
            $averageFatigue30Days = $this->getMetricTrendsForCollection($fatigueMetrics, MetricType::MORNING_GENERAL_FATIGUE)['averages']['Derniers 30 jours'] ?? null;

            if ($averageFatigue7Days !== null && $averageFatigue7Days >= $fatigueThresholds['persistent_high_7d_min'] && $averageFatigue30Days >= $fatigueThresholds['persistent_high_30d_min']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Fatigue générale très élevée persistante (moy. 7j: '.round($averageFatigue7Days).'/10). Potentiel signe de surentraînement ou manque de récupération.'];
            } elseif ($averageFatigue7Days !== null && $averageFatigue7Days >= $fatigueThresholds['elevated_7d_min'] && $averageFatigue30Days >= $fatigueThresholds['elevated_30d_min']) {
                $alerts[] = ['type' => 'info', 'message' => 'Fatigue générale élevée (moy. 7j: '.round($averageFatigue7Days).'/10). Surveiller la récupération.'];
            }
            $fatigueTrend = $this->getEvolutionTrendForCollection($fatigueMetrics, MetricType::MORNING_GENERAL_FATIGUE);
            if ($fatigueTrend['trend'] === 'increasing' && $fatigueTrend['change'] > $fatigueThresholds['trend_increase_percent']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Augmentation significative de la fatigue générale (+'.number_format($fatigueTrend['change'], 1).'%).'];
            }
        }

        // 2. Diminution de la qualité du sommeil (MORNING_SLEEP_QUALITY)
        $sleepMetrics = $metrics->filter(fn ($m) => $m->metric_type === MetricType::MORNING_SLEEP_QUALITY);
        $sleepThresholds = self::ALERT_THRESHOLDS[MetricType::MORNING_SLEEP_QUALITY->value];
        if ($sleepMetrics->count() > 5) {
            $averageSleep7Days = $this->getMetricTrendsForCollection($sleepMetrics, MetricType::MORNING_SLEEP_QUALITY)['averages']['Derniers 7 jours'] ?? null;
            $averageSleep30Days = $this->getMetricTrendsForCollection($sleepMetrics, MetricType::MORNING_SLEEP_QUALITY)['averages']['Derniers 30 jours'] ?? null;

            if ($averageSleep7Days !== null && $averageSleep7Days <= $sleepThresholds['persistent_low_7d_max'] && $averageSleep30Days <= $sleepThresholds['persistent_low_30d_max']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Qualité de sommeil très faible persistante (moy. 7j: '.round($averageSleep7Days).'/10). Peut affecter la récupération et la performance.'];
            }
            $sleepTrend = $this->getEvolutionTrendForCollection($sleepMetrics, MetricType::MORNING_SLEEP_QUALITY);
            if ($sleepTrend['trend'] === 'decreasing' && $sleepTrend['change'] < $sleepThresholds['trend_decrease_percent']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Diminution significative de la qualité du sommeil ('.number_format($sleepTrend['change'], 1).'%).'];
            }
        }

        // 3. Douleurs persistantes (MORNING_PAIN)
        $painMetrics = $metrics->filter(fn ($m) => $m->metric_type === MetricType::MORNING_PAIN);
        $painThresholds = self::ALERT_THRESHOLDS[MetricType::MORNING_PAIN->value];
        if ($painMetrics->count() > 5) {
            $averagePain7Days = $this->getMetricTrendsForCollection($painMetrics, MetricType::MORNING_PAIN)['averages']['Derniers 7 jours'] ?? null;
            if ($averagePain7Days !== null && $averagePain7Days >= $painThresholds['persistent_high_7d_min']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Douleurs musculaires/articulaires persistantes (moy. 7j: '.round($averagePain7Days)."/10). Évaluer la cause et la nécessité d'un repos."];
            }
            $painTrend = $this->getEvolutionTrendForCollection($painMetrics, MetricType::MORNING_PAIN);
            if ($painTrend['trend'] === 'increasing' && $painTrend['change'] > $painThresholds['trend_increase_percent']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Augmentation significative des douleurs (+'.number_format($painTrend['change'], 1).'%).'];
            }
        }

        // 4. Baisse de la VFC (MORNING_HRV) - indicateur de stress ou de fatigue
        $hrvMetrics = $metrics->filter(fn ($m) => $m->metric_type === MetricType::MORNING_HRV);
        $hrvThresholds = self::ALERT_THRESHOLDS[MetricType::MORNING_HRV->value];
        if ($hrvMetrics->count() > 5) {
            $hrvTrend = $this->getEvolutionTrendForCollection($hrvMetrics, MetricType::MORNING_HRV);
            if ($hrvTrend['trend'] === 'decreasing' && $hrvTrend['change'] < $hrvThresholds['trend_decrease_percent']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Diminution significative de la VFC ('.number_format($hrvTrend['change'], 1).'%). Peut indiquer un stress ou une fatigue accrue.'];
            }
        }

        // 5. Baisse du ressenti de performance (POST_SESSION_PERFORMANCE_FEEL)
        $perfFeelMetrics = $metrics->filter(fn ($m) => $m->metric_type === MetricType::POST_SESSION_PERFORMANCE_FEEL);
        $perfFeelThresholds = self::ALERT_THRESHOLDS[MetricType::POST_SESSION_PERFORMANCE_FEEL->value];
        if ($perfFeelMetrics->count() > 5) {
            $perfFeelTrend = $this->getEvolutionTrendForCollection($perfFeelMetrics, MetricType::POST_SESSION_PERFORMANCE_FEEL);
            if ($perfFeelTrend['trend'] === 'decreasing' && $perfFeelTrend['change'] < $perfFeelThresholds['trend_decrease_percent']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Diminution significative du ressenti de performance en séance ('.number_format($perfFeelTrend['change'], 1).'%).'];
            }
        }

        // 6. Faible poids corporel ou perte de poids rapide (MORNING_BODY_WEIGHT_KG)
        $weightMetrics = $metrics->filter(fn ($m) => $m->metric_type === MetricType::MORNING_BODY_WEIGHT_KG);
        $weightThresholds = self::ALERT_THRESHOLDS[MetricType::MORNING_BODY_WEIGHT_KG->value];
        if ($weightMetrics->count() > 5) {
            $weightTrend = $this->getEvolutionTrendForCollection($weightMetrics, MetricType::MORNING_BODY_WEIGHT_KG);
            if ($weightTrend['trend'] === 'decreasing' && $weightTrend['change'] < $weightThresholds['trend_decrease_percent']) {
                $alerts[] = ['type' => 'warning', 'message' => 'Perte de poids significative ('.number_format(abs($weightTrend['change']), 1).'%). Peut être un signe de déficit énergétique.'];
            }
        }

        // ** Alertes Spécifiques aux Femmes (potentiels signes de RED-S) **
        if ($athlete->gender === 'w') {
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
        } elseif (empty($alerts)) {
            // Si $alerts est vide et qu'il y a suffisamment de données (vérifié par le else if précédent),
            // cela signifie qu'aucune alerte préoccupante n'a été trouvée.
            $alerts[] = ['type' => 'success', 'message' => 'Aucune alerte, tout va bien.'];
        }

        return $alerts;
    }
}
