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
        $labelsAndData = [];
        $valueColumn = $metricType->getValueColumn();
        $unit = $metricType->getUnit();

        // Ensure metrics are sorted by date for chronological chart display
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
                // Prenez la première valeur numérique pour ce jour, ou null si aucune métrique ou valeur non numérique pour ce jour
                $value = $groupedMetrics->has($dateLabel) ? $groupedMetrics[$dateLabel]->first()->{$valueColumn} : null;
                $data[] = is_numeric($value) ? (float) $value : null;

                // Ajout pour labels_and_data
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
     * @param  int  $limit  Le nombre maximum de métriques brutes à récupérer.
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
            'trend_icon'                => 'ellipsis-horizontal', // default
            'trend_color'               => 'zinc', // default
            'trend_percentage'          => 'N/A',
            'chart_data'                => [],
            'is_numerical'              => ($valueColumn !== 'note'),
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

        return $formattedValue.($unit ? ' '.$unit : '');
    }

    /**
     * Déduit la phase du cycle menstruel d'un athlète féminin.
     */
    public function deduceMenstrualCyclePhase(Athlete $athlete): array
    {
        // Initialiser les valeurs par défaut
        $phase = 'Inconnue';
        $reason = 'Données de cycle non disponibles.';
        $averageCycleLength = null;
        $daysSinceLastPeriod = null;
        $lastPeriodStart = null;

        // Récupérer toutes les métriques J1 (Premier Jour des Règles) dans l'ordre chronologique inverse
        $j1Metrics = $athlete->metrics()
            ->where('metric_type', MetricType::MORNING_FIRST_DAY_PERIOD->value)
            ->where('value', 1) // Assurez-vous que la valeur est bien 1 pour un J1
            ->orderBy('date', 'desc') // Les plus récentes en premier
            ->get();

        // Déterminer le dernier J1 et les jours depuis le dernier J1
        if ($j1Metrics->isNotEmpty()) {
            $lastPeriodStart = Carbon::parse($j1Metrics->first()->date);
            $daysSinceLastPeriod = Carbon::now()->diffInDays($lastPeriodStart, true);
        }

        // Calculer les longueurs des cycles précédents et la moyenne
        $cycleLengths = [];
        if ($j1Metrics->count() >= 2) {
            // Parcourir les métriques pour calculer la durée de chaque cycle
            for ($i = 0; $i < $j1Metrics->count() - 1; $i++) {
                $start = Carbon::parse($j1Metrics[$i + 1]->date); // J1 précédent
                $end = Carbon::parse($j1Metrics[$i]->date);       // J1 actuel
                $cycleLengths[] = $end->diffInDays($start, true);
            }
            // Calculer la longueur moyenne du cycle
            $averageCycleLength = count($cycleLengths) > 0 ? array_sum($cycleLengths) / count($cycleLengths) : null;
        }

        // --- DÉDUCTION DE LA PHASE DU CYCLE MENSTRUEL PAR ORDRE DE PRIORITÉ ---

        // 1. Cas : Données insuffisantes pour établir un historique de cycle
        if ($j1Metrics->count() < 2 || $averageCycleLength === null) {
            $phase = 'Inconnue';
            $reason = 'Enregistrez au moins deux J1 pour calculer la durée moyenne de votre cycle.';
        }
        // 2. Cas : Aménorrhée (absence prolongée de règles) - La condition la plus grave
        // Définition: Absence de règles pendant au moins 90 jours (ou un seuil significativement plus long que la moyenne attendue).
        // Ici, si les jours passés depuis le dernier J1 dépassent la moyenne du cycle + 60 jours.
        elseif ($daysSinceLastPeriod !== null && $averageCycleLength !== null && $daysSinceLastPeriod > ($averageCycleLength + 60)) {
            $phase = 'Aménorrhée';
            $reason = 'Absence prolongée de règles (plus de 60 jours au-delà de la longueur moyenne du cycle attendue).';
        }
        // 3. Cas : Oligoménorrhée (longueur moyenne du cycle anormalement courte ou longue)
        // La longueur moyenne du cycle est en dehors de la plage normale (généralement 21-35 jours).
        // Cette condition prend le pas sur les phases normales si le cycle est globalement irrégulier.
        elseif ($averageCycleLength !== null && ($averageCycleLength < 21 || $averageCycleLength > 35)) {
            $phase = 'Oligoménorrhée';
            $reason = 'Longueur moyenne du cycle hors de la plage normale (21-35 jours).';
        }
        // 4. Cas : Potentiel retard ou cycle long (le cycle actuel dépasse la moyenne mais n'est pas Aménorrhée)
        // Si le cycle en cours a dépassé la longueur moyenne du cycle de plus de 5 jours (un seuil de "retard").
        elseif ($daysSinceLastPeriod !== null && $averageCycleLength !== null && $daysSinceLastPeriod >= $averageCycleLength + 5) {
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
            // Du jour 6 jusqu'au jour juste avant l'ovulation estimée (moyenne/2)
            elseif ($daysSinceLastPeriod > 5 && $daysSinceLastPeriod < ($averageCycleLength / 2)) {
                $phase = 'Folliculaire';
                $reason = 'Développement des follicules ovariens.';
            }
            // Phase Ovulatoire (autour de l'ovulation estimée)
            // Un fenêtre de 3 jours autour du milieu du cycle estimé (moyenne/2)
            elseif ($daysSinceLastPeriod >= ($averageCycleLength / 2) && $daysSinceLastPeriod <= ($averageCycleLength / 2) + 2) {
                $phase = 'Ovulatoire (estimée)';
                $reason = 'Libération de l\'ovule.';
            }
            // Phase Lutéale (post-ovulation, pré-prochaines règles)
            // Du jour après l'ovulation estimée jusqu'à la veille de la durée moyenne du cycle.
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
            'cycle_length_avg'  => $averageCycleLength !== null ? round($averageCycleLength) : null, // Arrondi pour l'affichage
            'last_period_start' => $lastPeriodStart ? $lastPeriodStart->format('d.m.Y') : null,
        ];
    }

    /**
     * Analyse les tendances des métriques et identifie des signaux d'alerte.
     * Cette fonction est générique pour tous les genres, mais aura des signaux spécifiques pour les femmes.
     *
     * @param  string  $period  Période pour l'analyse (ex: 'last_30_days', 'last_6_months')
     * @return array Des drapeaux et des messages d'alerte.
     */
    public function getAthleteAlerts(Athlete $athlete, string $period = 'last_60_days'): array
    {
        $alerts = [];
        $metrics = $this->getAthleteMetrics($athlete, ['period' => $period]);

        // ** Alertes Générales (Hommes et Femmes) **

        // 1. Fatigue générale persistante (MORNING_GENERAL_FATIGUE)
        $fatigueMetrics = $metrics->filter(fn ($m) => $m->metric_type === MetricType::MORNING_GENERAL_FATIGUE);
        if ($fatigueMetrics->count() > 5) { // Nécessite suffisamment de données
            $averageFatigue7Days = $this->getMetricTrendsForCollection($fatigueMetrics, MetricType::MORNING_GENERAL_FATIGUE)['averages']['Derniers 7 jours'] ?? null;
            $averageFatigue30Days = $this->getMetricTrendsForCollection($fatigueMetrics, MetricType::MORNING_GENERAL_FATIGUE)['averages']['Derniers 30 jours'] ?? null;

            if ($averageFatigue7Days !== null && $averageFatigue7Days >= 7 && $averageFatigue30Days >= 6) { // Ex: 7/10 en moyenne sur 7j, 6/10 sur 30j
                $alerts[] = ['type' => 'warning', 'message' => 'Fatigue générale très élevée persistante (moy. 7j: '.round($averageFatigue7Days).'/10). Potentiel signe de surentraînement ou manque de récupération.'];
            } elseif ($averageFatigue7Days !== null && $averageFatigue7Days >= 5 && $averageFatigue30Days >= 5) {
                $alerts[] = ['type' => 'info', 'message' => 'Fatigue générale élevée (moy. 7j: '.round($averageFatigue7Days).'/10). Surveiller la récupération.'];
            }
            $fatigueTrend = $this->getEvolutionTrendForCollection($fatigueMetrics, MetricType::MORNING_GENERAL_FATIGUE);
            if ($fatigueTrend['trend'] === 'increasing' && $fatigueTrend['change'] > 15) { // Augmentation de plus de 15%
                $alerts[] = ['type' => 'warning', 'message' => 'Augmentation significative de la fatigue générale (+'.number_format($fatigueTrend['change'], 1).'%).'];
            }
        }

        // 2. Diminution de la qualité du sommeil (MORNING_SLEEP_QUALITY)
        $sleepMetrics = $metrics->filter(fn ($m) => $m->metric_type === MetricType::MORNING_SLEEP_QUALITY);
        if ($sleepMetrics->count() > 5) {
            $averageSleep7Days = $this->getMetricTrendsForCollection($sleepMetrics, MetricType::MORNING_SLEEP_QUALITY)['averages']['Derniers 7 jours'] ?? null;
            $averageSleep30Days = $this->getMetricTrendsForCollection($sleepMetrics, MetricType::MORNING_SLEEP_QUALITY)['averages']['Derniers 30 jours'] ?? null;

            if ($averageSleep7Days !== null && $averageSleep7Days <= 4 && $averageSleep30Days <= 5) { // Ex: 4/10 en moyenne sur 7j, 5/10 sur 30j
                $alerts[] = ['type' => 'warning', 'message' => 'Qualité de sommeil très faible persistante (moy. 7j: '.round($averageSleep7Days).'/10). Peut affecter la récupération et la performance.'];
            }
            $sleepTrend = $this->getEvolutionTrendForCollection($sleepMetrics, MetricType::MORNING_SLEEP_QUALITY);
            if ($sleepTrend['trend'] === 'decreasing' && $sleepTrend['change'] < -15) { // Diminution de plus de 15%
                $alerts[] = ['type' => 'warning', 'message' => 'Diminution significative de la qualité du sommeil ('.number_format($sleepTrend['change'], 1).'%).'];
            }
        }

        // 3. Douleurs persistantes (MORNING_PAIN)
        $painMetrics = $metrics->filter(fn ($m) => $m->metric_type === MetricType::MORNING_PAIN);
        if ($painMetrics->count() > 5) {
            $averagePain7Days = $this->getMetricTrendsForCollection($painMetrics, MetricType::MORNING_PAIN)['averages']['Derniers 7 jours'] ?? null;
            if ($averagePain7Days !== null && $averagePain7Days >= 5) { // Ex: 5/10 en moyenne sur 7j
                $alerts[] = ['type' => 'warning', 'message' => 'Douleurs musculaires/articulaires persistantes (moy. 7j: '.round($averagePain7Days)."/10). Évaluer la cause et la nécessité d'un repos."];
            }
            $painTrend = $this->getEvolutionTrendForCollection($painMetrics, MetricType::MORNING_PAIN);
            if ($painTrend['trend'] === 'increasing' && $painTrend['change'] > 20) { // Augmentation de plus de 20%
                $alerts[] = ['type' => 'warning', 'message' => 'Augmentation significative des douleurs (+'.number_format($painTrend['change'], 1).'%).'];
            }
        }

        // 4. Baisse de la VFC (MORNING_HRV) - indicateur de stress ou de fatigue
        $hrvMetrics = $metrics->filter(fn ($m) => $m->metric_type === MetricType::MORNING_HRV);
        if ($hrvMetrics->count() > 5) {
            $hrvTrend = $this->getEvolutionTrendForCollection($hrvMetrics, MetricType::MORNING_HRV);
            if ($hrvTrend['trend'] === 'decreasing' && $hrvTrend['change'] < -10) { // Diminution de plus de 10%
                $alerts[] = ['type' => 'warning', 'message' => 'Diminution significative de la VFC ('.number_format($hrvTrend['change'], 1).'%). Peut indiquer un stress ou une fatigue accrue.'];
            }
        }

        // 5. Baisse du ressenti de performance (POST_SESSION_PERFORMANCE_FEEL)
        $perfFeelMetrics = $metrics->filter(fn ($m) => $m->metric_type === MetricType::POST_SESSION_PERFORMANCE_FEEL);
        if ($perfFeelMetrics->count() > 5) {
            $perfFeelTrend = $this->getEvolutionTrendForCollection($perfFeelMetrics, MetricType::POST_SESSION_PERFORMANCE_FEEL);
            if ($perfFeelTrend['trend'] === 'decreasing' && $perfFeelTrend['change'] < -15) {
                $alerts[] = ['type' => 'warning', 'message' => 'Diminution significative du ressenti de performance en séance ('.number_format($perfFeelTrend['change'], 1).'%).'];
            }
        }

        // ** Alertes Spécifiques aux Femmes (potentiels signes de RED-S) **
        if ($athlete->gender === 'w') {
            $cycleData = $this->deduceMenstrualCyclePhase($athlete);

            // 1. Irrégularité ou absence de règles
            // Nécessite plus de données que le simple J1 pour être précis, idéalement un historique sur 3-6 mois
            // Pour l'instant, on se base sur la longueur du cycle moyenne calculée.

            // 1. Aménorrhée ou Oligoménorrhée (détecté par la phase calculée)
            if ($cycleData['phase'] === 'Aménorrhée' || $cycleData['phase'] === 'Oligoménorrhée') {
                $alerts[] = [
                    'type'    => 'danger',
                    'message' => "Cycle menstruel irrégulier (moy. {$cycleData['cycle_length_avg']} jours). Il est suggéré de consulter pour évaluer un potentiel RED-S.",
                ];
            }
            // 2. Absence de règles prolongée sans données de cycle moyen (cas où 'deduceMenstrualCyclePhase' n'aurait pas pu déterminer la phase 'Aménorrhée' faute de données de moyenne)
            // Ceci est une condition de secours si l'Aménorrhée n'est pas déjà détectée par la phase elle-même,
            // par exemple s'il n'y a qu'un J1 très ancien sans historique.
            // On s'assure que cette alerte ne s'ajoute que si aucune alerte de danger majeure n'a déjà été ajoutée pour le cycle.
            elseif (empty($alerts) && $cycleData['cycle_length_avg'] === null && $cycleData['last_period_start']) {
                $daysSinceLastPeriod = Carbon::parse($cycleData['last_period_start'])->diffInDays(Carbon::now());
                if ($daysSinceLastPeriod > 45) {
                    $alerts[] = ['type' => 'danger', 'message' => 'Absence de règles prolongée ('.$daysSinceLastPeriod.' jours depuis les dernières règles). Forte suspicion de RED-S. Consultation médicale impérative.'];
                }
            }
            // 3. Potentiel retard ou cycle long avec une moyenne de cycle NORMAL (21-35 jours)
            // Ceci gère le scénario où le cycle est en retard, mais l'athlète a des antécédents de cycles réguliers.
            elseif (empty($alerts) && $cycleData['phase'] === 'Potentiel retard ou cycle long' && $cycleData['cycle_length_avg'] >= 21 && $cycleData['cycle_length_avg'] <= 35) {
                $alerts[] = [
                    'type'    => 'warning',
                    'message' => "Retard du cycle menstruel (moy. {$cycleData['cycle_length_avg']} jours). Suggéré de surveiller.",
                ];
            }
            // 4. Phase 'Inconnue' en raison de l'absence de données J1 (priorité faible)
            // Ceci devrait être la dernière condition de cycle pour les problèmes de données.
            elseif (empty($alerts) && $cycleData['phase'] === 'Inconnue' && $cycleData['reason'] === 'Pas de données sur le premier jour des règles.') {
                $alerts[] = ['type' => 'info', 'message' => 'Aucune donnée récente sur le premier jour des règles pour cette athlète. Un suivi est recommandé.'];
            }

            // 3. Corrélation entre phase menstruelle et performance/fatigue
            // Ceci est une analyse plus avancée et nécessiterait de croiser les données.
            // Par exemple, si la performance ressentie (POST_SESSION_PERFORMANCE_FEEL) ou l'énergie (PRE_SESSION_ENERGY_LEVEL)
            // sont constamment plus basses pendant la phase menstruelle ou lutéale, et que la fatigue est plus haute.
            // C'est plus une "insight" qu'une "alerte" directe pour le RED-S ici, mais peut indiquer une mauvaise gestion du cycle.
            if ($cycleData['phase'] === 'Menstruelle') {
                $currentDayFatigue = $metrics->firstWhere('metric_type', MetricType::MORNING_GENERAL_FATIGUE)?->value;
                $currentDayPerformanceFeel = $metrics->firstWhere('metric_type', MetricType::POST_SESSION_PERFORMANCE_FEEL)?->value;

                if ($currentDayFatigue !== null && $currentDayFatigue >= 7) {
                    $alerts[] = ['type' => 'info', 'message' => 'Fatigue élevée ('.$currentDayFatigue."/10) pendant la phase menstruelle. Adapter l'entraînement peut être bénéfique."];
                }
                if ($currentDayPerformanceFeel !== null && $currentDayPerformanceFeel <= 4) {
                    $alerts[] = ['type' => 'info', 'message' => 'Performance ressentie faible ('.$currentDayPerformanceFeel."/10) pendant la phase menstruelle. Évaluer l'intensité de l'entraînement."];
                }
            }
        }

        // 2. Faible poids corporel ou perte de poids rapide (MORNING_BODY_WEIGHT_KG)
        // C'est un indicateur complexe et sensible. À manier avec précaution.
        // Il faudrait idéalement une connaissance du poids "sain" de l'athlète.
        // Ici, nous nous basons sur une diminution significative du poids.
        $weightMetrics = $metrics->filter(fn ($m) => $m->metric_type === MetricType::MORNING_BODY_WEIGHT_KG);
        if ($weightMetrics->count() > 5) {
            $weightTrend = $this->getEvolutionTrendForCollection($weightMetrics, MetricType::MORNING_BODY_WEIGHT_KG);
            if ($weightTrend['trend'] === 'decreasing' && $weightTrend['change'] < -3) { // Perte de plus de 3% du poids sur la période
                $alerts[] = ['type' => 'warning', 'message' => 'Perte de poids significative ('.number_format(abs($weightTrend['change']), 1).'%). Peut être un signe de déficit énergétique.'];
            }
        }

        // Si aucune alerte mais suffisamment de données, on peut donner un statut "RAS"
        if (empty($alerts) && $metrics->isNotEmpty()) {
            $alerts[] = ['type' => 'success', 'message' => 'Aucune alerte détectée.'];
        } elseif (empty($alerts)) {
            $alerts[] = ['type' => 'info', 'message' => 'Pas encore suffisamment de données pour une analyse complète.'];
        }

        return $alerts;
    }
}
