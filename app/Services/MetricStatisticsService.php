<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Metric;
use App\Models\Athlete; // Import de l'énumération
use App\Enums\MetricType;
use Illuminate\Support\Collection;

// Pour les agrégations

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
            // Assurez-vous que le metric_type passé est valide pour l'énumération
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
                $query->where('date', '>=', $now->subDays(7)->startOfDay());
                break;
            case 'last_30_days':
                $query->where('date', '>=', $now->subDays(30)->startOfDay());
                break;
            case 'last_6_months':
                $query->where('date', '>=', $now->subMonths(6)->startOfDay());
                break;
            case 'last_year':
                $query->where('date', '>=', $now->subYear()->startOfDay());
                break;
            case 'all_time':
                // Pas de filtre de date spécifique
                break;
            default:
                // Pour une période personnalisée (ex: 'custom:2023-01-01,2023-12-31')
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
     * Prépare les données d'une seule métrique pour l'affichage sur un graphique Flux UI.
     *
     * @param  Collection<Metric>  $metrics
     * @param  MetricType  $metricType  L'énumération MetricType pour obtenir les détails du champ de valeur.
     * @return array ['labels' => [], 'data' => [], 'unit' => string|null]
     */
    public function prepareChartDataForSingleMetric(Collection $metrics, MetricType $metricType): array
    {
        $labels = [];
        $data = [];
        $valueColumn = $metricType->getValueColumn(); // 'value' ou 'note'
        $unit = $metricType->getUnit();

        foreach ($metrics as $metric) {
            // Assurez-vous que la métrique correspond au type demandé si la collection contient plusieurs types
            if ($metric->metric_type === $metricType) {
                $labels[] = $metric->date->format('Y-m-d'); // Format YYYY-MM-DD pour la simplicité
                $data[] = $metric->{$valueColumn};
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
     * Prépare les données de plusieurs métriques pour l'affichage sur un graphique Flux UI.
     * Utile pour comparer différentes métriques sur un même axe ou des axes multiples.
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

            // Grouper les métriques par date pour ce type de métrique
            $groupedMetrics = $metrics->filter(fn ($m) => $m->metric_type === $metricType)
                ->groupBy(fn ($m) => $m->date->format('Y-m-d'));

            $data = [];
            foreach ($allLabels as $dateLabel) {
                // Prenez la première valeur pour ce jour, ou null si aucune métrique pour ce jour
                $data[] = $groupedMetrics->has($dateLabel) ? $groupedMetrics[$dateLabel]->first()->{$valueColumn} : null;
            }

            $datasets[] = [
                'label' => $label.($unit ? ' ('.$unit.')' : ''),
                'data'  => $data,
                // Ajoutez d'autres options spécifiques à Flux UI si nécessaire, ex: 'type' => 'line'
            ];
        }

        return [
            'labels'   => $allLabels,
            'datasets' => $datasets,
        ];
    }

    /**
     * Calcule la moyenne des métriques sur différentes périodes.
     */
    public function getMetricTrends(Athlete $athlete, MetricType $metricType): array
    {
        $trends = [];
        $valueColumn = $metricType->getValueColumn();

        $periods = [
            'Derniers 7 jours'  => 7,
            'Derniers 30 jours' => 30,
            'Derniers 90 jours' => 90,
            'Derniers 6 mois'   => 180,
            'Derniers 1 an'     => 365,
        ];

        foreach ($periods as $label => $days) {
            $startDate = Carbon::now()->subDays($days)->startOfDay();
            $average = $athlete->metrics()
                ->where('metric_type', $metricType->value)
                ->where('date', '>=', $startDate)
                ->average($valueColumn);

            $trends[$label] = $average;
        }

        // Calculer la moyenne globale (all time)
        $allTimeAverage = $athlete->metrics()
            ->where('metric_type', $metricType->value)
            ->average($valueColumn);
        $trends['Total'] = $allTimeAverage;

        return [
            'metric_label' => $metricType->getLabel(),
            'unit'         => $metricType->getUnit(),
            'averages'     => $trends,
        ];
    }
}
