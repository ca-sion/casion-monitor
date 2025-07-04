<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Metric;
use App\Models\Athlete;
use App\Enums\MetricType;
use Illuminate\Support\Collection;

class MetricStatisticsService
{
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
                // Pas de filtre de date spécifique
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
        $valueColumn = $metricType->getValueColumn();
        $unit = $metricType->getUnit();

        // Ensure metrics are sorted by date for chronological chart display
        $sortedMetrics = $metrics->sortBy('date');

        foreach ($sortedMetrics as $metric) {
            if ($metric->metric_type === $metricType) {
                $labels[] = $metric->date->format('Y-m-d');
                $value = $metric->{$valueColumn};
                $data[] = is_numeric($value) ? (float) $value : null; // S'assurer que la valeur est numérique ou null
            }
        }

        return [
            'labels' => $labels,
            'data'   => $data,
            'unit'   => $unit,
            'label'  => $metricType->getLabel(),
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

        foreach ($metricTypes as $metricType) {
            $valueColumn = $metricType->getValueColumn();
            $label = $metricType->getLabel();
            $unit = $metricType->getUnit();

            $groupedMetrics = $metrics->filter(fn ($m) => $m->metric_type === $metricType)
                ->groupBy(fn ($m) => $m->date->format('Y-m-d'));

            $data = [];
            foreach ($allLabels as $dateLabel) {
                // Prenez la première valeur numérique pour ce jour, ou null si aucune métrique ou valeur non numérique pour ce jour
                $value = $groupedMetrics->has($dateLabel) ? $groupedMetrics[$dateLabel]->first()->{$valueColumn} : null;
                $data[] = is_numeric($value) ? (float) $value : null;
            }

            $datasets[] = [
                'label' => $label.($unit ? ' ('.$unit.')' : ''),
                'data'  => $data,
            ];
        }

        return [
            'labels'   => $allLabels,
            'datasets' => $datasets,
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
     * Version de getMetricTrends qui prend une collection déjà filtrée.
     * Utile pour éviter des requêtes supplémentaires.
     *
     * @param  Collection<Metric>  $metrics
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
     * @param Collection<Metric> $metrics Collection de métriques déjà filtrée par type.
     * @return array ['trend' => 'increasing'|'decreasing'|'stable'|'N/A', 'change' => float|null, 'reason' => string|null]
     */
    public function getEvolutionTrendForCollection(Collection $metrics, MetricType $metricType): array
    {
        if ($metricType->getValueColumn() === 'note') {
            return ['trend' => 'N/A', 'change' => null, 'reason' => 'La métrique n\'est pas numérique.'];
        }

        $valueColumn = $metricType->getValueColumn();
        $numericMetrics = $metrics->filter(fn($m) => is_numeric($m->{$valueColumn}))->sortBy('date');

        if ($numericMetrics->count() < 2) {
            return ['trend' => 'N/A', 'change' => null, 'reason' => 'Pas assez de données pour calculer une tendance.'];
        }

        // Calculate average of the first third and last third of the data points for a smoother trend
        $totalCount = $numericMetrics->count();
        $segmentSize = floor($totalCount / 3);

        if ($segmentSize === 0) { // If less than 3 data points, compare first and last
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
        
        // Handle division by zero for percentage change
        if ($firstValue == 0 && $lastValue != 0) {
            $change = 100; // Represents a significant increase from zero
        } elseif ($firstValue == 0 && $lastValue == 0) {
            $change = 0; // No change if both are zero
        } else {
            $change = (($lastValue - $firstValue) / $firstValue) * 100;
        }

        $trend = 'stable';
        // Define a small threshold to consider it stable, avoiding micro-fluctuations
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
     * @param Athlete $athlete
     * @param int $limit Le nombre maximum de métriques brutes à récupérer.
     * @return Collection<string, Collection<Metric>> Une collection de métriques groupées par date (format Y-m-d).
     */
    public function getLatestMetricsGroupedByDate(Athlete $athlete, int $limit = 50): Collection
    {
        // On récupère toutes les métriques pour cet athlète, limitées par date distincte.
        // Puis on les groupe par date en PHP.
        // Le `limit` ici s'applique au nombre de métriques brutes, pas au nombre de jours.
        // Pour limiter par jour, il faut une approche légèrement différente, par exemple:
        // Sélectionner les métriques des 50 derniers jours uniques.
        $metrics = $athlete->metrics()
            ->whereNotNull('date') // S'assurer que la date n'est pas nulle
            ->orderByDesc('date')
            ->limit($limit * count(MetricType::cases())) // Multiplier la limite pour être sûr d'avoir des données pour plusieurs types sur plusieurs jours
            ->get();

        // Regrouper par date et s'assurer que les dates sont triées de la plus récente à la plus ancienne
        return $metrics->groupBy(fn ($metric) => $metric->date->format('Y-m-d'))
                       ->sortByDesc(fn ($metrics, $date) => $date);
    }

    /**
     * Prépare toutes les données agrégées pour le tableau de bord d'une métrique spécifique.
     *
     * @param Athlete $athlete
     * @param MetricType $metricType
     * @param string $period
     * @return array
     */
    public function getDashboardMetricData(Athlete $athlete, MetricType $metricType, string $period): array
    {
        $metricsForPeriod = $this->getAthleteMetrics($athlete, ['metric_type' => $metricType->value, 'period' => $period]);

        $valueColumn = $metricType->getValueColumn();

        $metricData = [
            'label'           => $metricType->getLabel(),
            'short_label'     => $metricType->getLabelShort(),
            'description'     => $metricType->getDescription(),
            'unit'            => $metricType->getUnit(),
            'last_value'      => null,
            'formatted_last_value' => 'N/A',
            'average_7_days'  => null,
            'formatted_average_7_days' => 'N/A',
            'average_30_days' => null,
            'formatted_average_30_days' => 'N/A',
            'trend_icon'      => 'ellipsis-horizontal', // default
            'trend_color'     => 'zinc', // default
            'trend_percentage' => 'N/A',
            'chart_data'      => [],
            'is_numerical'    => ($valueColumn !== 'note'),
        ];

        // Prepare chart data
        $metricData['chart_data'] = $this->prepareChartDataForSingleMetric($metricsForPeriod, $metricType);
        
        // Get last value
        // Use the metrics from the current period for the last value, if available
        $lastMetric = $metricsForPeriod->sortByDesc('date')->first();
        if ($lastMetric) {
            $metricValue = $lastMetric->{$valueColumn};
            $metricData['last_value'] = $metricValue;
            $metricData['formatted_last_value'] = $this->formatMetricValue($metricValue, $metricType);
        }

        // Only calculate trends and evolution for numerical metrics
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
            
            // Trend percentage - calculate change from 30 days to 7 days if applicable
            if ($metricData['average_7_days'] !== null && $metricData['average_30_days'] !== null && $metricData['average_30_days'] !== 0) {
                $change = (($metricData['average_7_days'] - $metricData['average_30_days']) / $metricData['average_30_days']) * 100;
                $metricData['trend_percentage'] = ($change > 0 ? '+' : '').number_format($change, 1).'%';
            } elseif ($metricData['average_7_days'] !== null && $metricType->getValueColumn() !== 'note') {
                 // If only 7-day average exists and it's numerical, format its value for percentage
                 $metricData['trend_percentage'] = $this->formatMetricValue($metricData['average_7_days'], $metricType);
            }
        }

        return $metricData;
    }

    /**
     * Formate une valeur de métrique en fonction de son type et de sa précision.
     *
     * @param mixed $value
     * @param MetricType $metricType
     * @return string
     */
    protected function formatMetricValue(mixed $value, MetricType $metricType): string
    {
        if ($value === null) {
            return 'N/A';
        }
        if ($metricType->getValueColumn() === 'note') {
            return (string) $value;
        }

        $formattedValue = number_format($value, $metricType->getPrecision());
        $unit = $metricType->getUnit();

        return $formattedValue . ($unit ? ' ' . $unit : '');
    }
}