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

        foreach ($metrics as $metric) {
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
     * @param  string  $period  Période pour l'analyse (ex: 'last_30_days', 'last_6_months')
     * @param  int  $comparisonDays  Nombre de jours pour calculer la moyenne au début/fin (ex: 7 pour la moyenne sur 7 jours)
     * @return array ['trend' => 'increasing'|'decreasing'|'stable'|'not_enough_data'|'not_applicable', 'change' => float|null, 'unit' => string|null, 'start_value' => float|null, 'end_value' => float|null, 'label' => string, 'period' => string, 'reason' => string|null]
     */
    public function getMetricEvolutionTrend(Athlete $athlete, MetricType $metricType, string $period, int $comparisonDays = 7): array
    {
        if ($metricType->getValueColumn() === 'note') {
            return [
                'trend'       => 'not_applicable',
                'change'      => null,
                'start_value' => null,
                'end_value'   => null,
                'unit'        => $metricType->getUnit(),
                'label'       => $metricType->getLabel(),
                'period'      => $period,
                'reason'      => 'La métrique n\'est pas numérique et ne peut pas être analysée pour une tendance d\'évolution.',
            ];
        }

        $query = $athlete->metrics()
            ->where('metric_type', $metricType->value)
            ->orderBy('date', 'asc');

        $this->applyPeriodFilter($query, $period);

        $metrics = $query->get();

        return $this->getMetricEvolutionTrendForCollection($metrics, $metricType, $period, $comparisonDays);
    }

    /**
     * Version de getMetricEvolutionTrend qui prend une collection déjà filtrée.
     *
     * @param  Collection<Metric>  $metrics
     */
    public function getMetricEvolutionTrendForCollection(Collection $metrics, MetricType $metricType, string $period, int $comparisonDays = 7): array
    {
        if ($metricType->getValueColumn() === 'note') {
            return [
                'trend'       => 'not_applicable',
                'change'      => null,
                'start_value' => null,
                'end_value'   => null,
                'unit'        => $metricType->getUnit(),
                'label'       => $metricType->getLabel(),
                'period'      => $period,
                'reason'      => 'La métrique n\'est pas numérique et ne peut pas être analysée pour une tendance d\'évolution.',
            ];
        }

        $valueColumn = $metricType->getValueColumn();
        $unit = $metricType->getUnit();

        // Assurez-vous que la collection est triée par date pour un découpage précis.
        $metrics = $metrics->sortBy('date');

        if ($metrics->count() < 2) {
            return [
                'trend'       => 'not_enough_data',
                'change'      => null,
                'start_value' => null,
                'end_value'   => null,
                'unit'        => $unit,
                'label'       => $metricType->getLabel(),
                'period'      => $period,
            ];
        }

        // Calcule la moyenne des $comparisonDays premières entrées numériques
        $startMetrics = $metrics->filter(fn ($m) => is_numeric($m->{$valueColumn}))->take($comparisonDays);
        $startValue = $startMetrics->avg($valueColumn);

        // Calcule la moyenne des $comparisonDays dernières entrées numériques
        $endMetrics = $metrics->filter(fn ($m) => is_numeric($m->{$valueColumn}))->slice($metrics->count() - $comparisonDays);
        $endValue = $endMetrics->avg($valueColumn);

        // Fallback vers les première et dernière valeurs numériques si les moyennes sont nulles ou non numériques.
        if ($startValue === null || $endValue === null || ! is_numeric($startValue) || ! is_numeric($endValue)) {
            $firstNumericMetric = $metrics->first(fn ($m) => is_numeric($m->{$valueColumn}));
            $lastNumericMetric = $metrics->last(fn ($m) => is_numeric($m->{$valueColumn}));

            if ($firstNumericMetric && $lastNumericMetric) {
                $startValue = (float) $firstNumericMetric->{$valueColumn};
                $endValue = (float) $lastNumericMetric->{$valueColumn};
            } else {
                return [
                    'trend'       => 'not_enough_data',
                    'change'      => null,
                    'start_value' => null,
                    'end_value'   => null,
                    'unit'        => $unit,
                    'label'       => $metricType->getLabel(),
                    'period'      => $period,
                    'reason'      => 'Aucune donnée numérique valide trouvée pour la tendance.',
                ];
            }
        }

        $change = $endValue - $startValue;
        $trend = 'stable';

        if ($change > 0.001) { // Utiliser un epsilon pour la comparaison des flottants
            $trend = 'increasing';
        } elseif ($change < -0.001) {
            $trend = 'decreasing';
        }

        return [
            'trend'       => $trend,
            'change'      => $change,
            'start_value' => $startValue,
            'end_value'   => $endValue,
            'unit'        => $unit,
            'label'       => $metricType->getLabel(),
            'period'      => $period,
        ];
    }

    /**
     * Récupère et prépare les données de métriques pour plusieurs athlètes
     * et plusieurs types de métriques pour une vue d'ensemble.
     * Optimisé pour réduire le nombre de requêtes N+1.
     *
     * @param  Collection<Athlete>  $athletes  La collection d'athlètes à analyser.
     * @param  array  $metricTypeValues  Les valeurs des énumérations MetricType à inclure.
     * @param  string  $period  La période à filtrer.
     * @return array Tableau associatif où la clé est l'ID de l'athlète, contenant les données agrégées.
     *               Ex: ['athlete_id' => ['trends' => [], 'chart_data' => []]]
     */
    public function getOverviewMetricsForAthletes(Collection $athletes, array $metricTypeValues, string $period): array
    {
        $athleteIds = $athletes->pluck('id')->toArray();
        $metricTypes = collect($metricTypeValues)
            ->map(fn ($value) => MetricType::tryFrom($value))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($athleteIds) || empty($metricTypes)) {
            return [];
        }

        // 1. Charger toutes les métriques pertinentes en une seule requête pour tous les athlètes
        $query = Metric::whereIn('athlete_id', $athleteIds)
            ->whereIn('metric_type', array_map(fn ($mt) => $mt->value, $metricTypes))
            ->orderBy('date', 'asc');

        $this->applyPeriodFilter($query, $period);

        $allMetrics = $query->get()->groupBy('athlete_id'); // Groupe par athlète_id

        $results = [];

        foreach ($athletes as $athlete) {
            $athleteMetrics = $allMetrics->get($athlete->id, new Collection); // Obtenir les métriques pour cet athlète

            $athleteData = [
                'athlete'          => $athlete->only(['id', 'first_name', 'last_name', 'name']), // Informations basiques de l'athlète
                'trends'           => [],
                'evolution_trends' => [],
                'chart_data'       => [],
            ];

            foreach ($metricTypes as $metricType) {
                // Filtrer les métriques spécifiques à ce type de métrique pour l'athlète courant
                $singleMetricMetrics = $athleteMetrics->filter(function ($m) use ($metricType) {
                    return $m->metric_type === $metricType;
                });

                // Prépare les données du graphique pour ce type de métrique (pour un potentiel graphique individuel)
                $athleteData['chart_data'][$metricType->value] = $this->prepareChartDataForSingleMetric($singleMetricMetrics, $metricType);

                // Calcule les tendances moyennes
                $athleteData['trends'][$metricType->value] = $this->getMetricTrendsForCollection($singleMetricMetrics, $metricType);

                // Calcule la tendance d'évolution
                $athleteData['evolution_trends'][$metricType->value] = $this->getMetricEvolutionTrendForCollection($singleMetricMetrics, $metricType, $period);
            }
            $results[$athlete->id] = $athleteData;
        }

        return $results;
    }
}
