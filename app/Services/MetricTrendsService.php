<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Metric;
use App\Models\Athlete;
use App\Enums\MetricType;
use Illuminate\Support\Collection;
use App\Enums\CalculatedMetricType;

class MetricTrendsService
{
    /**
     * Calcule la moyenne des métriques sur différentes périodes pour un athlète.
     */
    public function calculateAthleteMetricAverages(Athlete $athlete, MetricType $metricType): array
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

        return $this->calculateMetricAveragesFromCollection($metrics, $metricType);
    }

    /**
     * Calcule la moyenne des métriques calculées sur différentes périodes pour un athlète.
     */
    public function calculateAthleteCalculatedMetricAverages(Athlete $athlete, CalculatedMetricType $calculatedMetricType): array
    {
        $query = $athlete->calculatedMetrics()
            ->where('type', $calculatedMetricType->value)
            ->orderBy('date', 'asc');

        $metrics = $query->get();

        return $this->calculateCalculatedMetricAveragesFromCollection($metrics, $calculatedMetricType);
    }

    /**
     * Calcule les tendances des métriques sur différentes périodes à partir d'une collection déjà filtrée.
     *
     * @param  Collection<Metric>  $metrics  La collection de métriques à analyser.
     */
    public function calculateMetricAveragesFromCollection(Collection $metrics, MetricType $metricType): array
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
     * Calcule les tendances des métriques calculées sur différentes périodes à partir d'une collection déjà filtrée.
     *
     * @param  Collection<CalculatedMetric>  $calculatedMetrics  La collection de métriques calculées à analyser.
     */
    public function calculateCalculatedMetricAveragesFromCollection(Collection $calculatedMetrics, CalculatedMetricType $calculatedMetricType): array
    {
        $trends = [];
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
            $average = $calculatedMetrics->filter(fn ($m) => $m->date && $m->date->greaterThanOrEqualTo($startDate) && is_numeric($m->value))->avg('value');
            $trends[$label] = $average;
        }

        $allTimeAverage = $calculatedMetrics->filter(fn ($m) => is_numeric($m->value))->avg('value');
        $trends['Total'] = $allTimeAverage;

        return [
            'metric_label' => $calculatedMetricType->getLabel(),
            'unit'         => $calculatedMetricType->getScale(), // Use getScale() for unit/scale if applicable
            'averages'     => $trends,
        ];
    }

    /**
     * Calcule la tendance d\'évolution (accroissement/décroissement) pour une métrique sur une période.
     * Compare la valeur moyenne au début et à la fin de la période ou la première/dernière valeur.
     *
     * @param  Collection<Metric|\App\Models\CalculatedMetric>  $metrics  Collection de métriques déjà filtrée par type.
     * @return array ['trend' => 'increasing'|'decreasing'|'stable'|'n/a', 'change' => float|null, 'reason' => string|null]
     */
    public function calculateMetricEvolutionTrend(Collection $metrics, MetricType|CalculatedMetricType $metricType): array
    {
        if ($metricType instanceof MetricType && $metricType->getValueColumn() === 'note') {
            return ['trend' => 'n/a', 'change' => null, 'reason' => 'La métrique n\'est pas numérique.'];
        }

        $valueColumn = $metricType instanceof MetricType ? $metricType->getValueColumn() : 'value';
        $numericMetrics = $metrics->filter(fn ($m) => is_numeric($m->{$valueColumn}))->sortBy('date');

        if ($numericMetrics->count() < 2) {
            return ['trend' => 'n/a', 'change' => null, 'reason' => 'Pas assez de données pour calculer une tendance.'];
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
            return ['trend' => 'n/a', 'change' => null, 'reason' => 'Impossible de calculer la tendance avec les valeurs fournies.'];
        }

        if ($firstValue == 0 && $lastValue != 0) {
            $change = 100; // Augmentation significative à partir de zéro.
        } elseif ($firstValue == 0 && $lastValue == 0) {
            $change = 0; // Aucun changement si les deux sont zéro.
        } else {
            $change = (($lastValue - $firstValue) / abs($firstValue)) * 100;
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
     * Calcule la tendance d\'évolution pour une collection de valeurs numériques et de dates.
     * Cette méthode est utilisée pour les métriques synthétiques comme le SBM qui ne sont pas directement des "MetricType".
     *
     * @param  Collection<object|array>  $dataCollection  Collection d\'objets/tableaux avec 'date' et 'value'.
     * @return array ['trend' => 'increasing'|'decreasing'|'stable'|'n/a', 'change' => float|null, 'reason' => string|null]
     */
    public function calculateGenericNumericTrend(Collection $dataCollection): array
    {
        $numericData = $dataCollection->filter(fn ($item) => is_numeric($item->value ?? $item['value'] ?? null))
            ->sortBy(fn ($item) => $item->date ?? $item['date']);

        if ($numericData->count() < 2) {
            return ['trend' => 'n/a', 'change' => null, 'reason' => 'Pas assez de données pour calculer une tendance.'];
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
            return ['trend' => 'n/a', 'change' => null, 'reason' => 'Impossible de calculer la tendance avec les valeurs fournies.'];
        }

        if ($firstValue == 0 && $lastValue != 0) {
            $change = 100;
        } elseif ($firstValue == 0 && $lastValue == 0) {
            $change = 0;
        } else {
            $change = (($lastValue - $firstValue) / abs($firstValue)) * 100;
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
     * Compte le nombre de jours de "Damping" (amortissement psychologique).
     */
    public function getDampingCount(Athlete $athlete, Carbon $startDate, Carbon $endDate): int
    {
        $rawMetrics = $athlete->metrics()
            ->whereIn('metric_type', [
                MetricType::MORNING_HRV->value,
                MetricType::MORNING_MOOD_WELLBEING->value,
            ])
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $sbmMetrics = $athlete->calculatedMetrics()
            ->where('type', CalculatedMetricType::SBM)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $hrvAvg = $rawMetrics->where('metric_type', MetricType::MORNING_HRV->value)->avg('value');
        $sbmAvg = $sbmMetrics->avg('value');

        if ($hrvAvg == 0 || $sbmAvg == 0) {
            return 0;
        }

        $dampingDays = 0;

        $sbmMetricsByDate = $sbmMetrics->keyBy(fn ($m) => $m->date->toDateString());
        $rawMetricsByDate = $rawMetrics->groupBy(fn ($m) => $m->date->toDateString());

        $commonDates = $sbmMetricsByDate->keys()->intersect($rawMetricsByDate->keys());

        foreach ($commonDates as $date) {
            $dailyRawMetrics = $rawMetricsByDate[$date];
            $hrv = $dailyRawMetrics->firstWhere('metric_type', MetricType::MORNING_HRV->value)?->value;
            $mood = $dailyRawMetrics->firstWhere('metric_type', MetricType::MORNING_MOOD_WELLBEING->value)?->value;
            $sbm = $sbmMetricsByDate[$date]->value;

            if ($mood === null || $mood < 8) {
                continue;
            }

            $physioFatigue = false;
            if ($hrv !== null && $hrv < $hrvAvg * 0.9) {
                $physioFatigue = true;
            }
            if ($sbm !== null && $sbm < $sbmAvg * 0.9) {
                $physioFatigue = true;
            }

            if ($physioFatigue) {
                $dampingDays++;
            }
        }

        return $dampingDays;
    }

    /**
     * Vérifie si on a assez de données pour une corrélation.
     */
    public function hasEnoughDataForCorrelation(Athlete $athlete, MetricType|CalculatedMetricType $metricTypeA, MetricType|CalculatedMetricType $metricTypeB, int $days): bool
    {
        $startDate = Carbon::now()->subDays($days);

        $countA = 0;
        if ($metricTypeA instanceof MetricType) {
            $countA = $athlete->metrics()->where('metric_type', $metricTypeA->value)->where('date', '>=', $startDate)->count();
        } elseif ($metricTypeA instanceof CalculatedMetricType) {
            $countA = $athlete->calculatedMetrics()->where('type', $metricTypeA->value)->where('date', '>=', $startDate)->count();
        }

        $countB = 0;
        if ($metricTypeB instanceof MetricType) {
            $countB = $athlete->metrics()->where('metric_type', $metricTypeB->value)->where('date', '>=', $startDate)->count();
        } elseif ($metricTypeB instanceof CalculatedMetricType) {
            $countB = $athlete->calculatedMetrics()->where('type', $metricTypeB->value)->where('date', '>=', $startDate)->count();
        }

        return min($countA, $countB) >= 5;
    }

    /**
     * Calcule la corrélation de Pearson entre deux types de métriques sur une période donnée.
     */
    public function calculateCorrelation(Athlete $athlete, MetricType|CalculatedMetricType $metricTypeA, MetricType|CalculatedMetricType $metricTypeB, int $days): array
    {
        $startDate = Carbon::now()->subDays($days);

        $collectionA = null;
        if ($metricTypeA instanceof MetricType) {
            $collectionA = $athlete->metrics()
                ->where('metric_type', $metricTypeA->value)
                ->where('date', '>=', $startDate)
                ->orderBy('date')
                ->get()->map(fn ($m) => (object) ['date' => $m->date->toDateString(), 'value' => $m->{$metricTypeA->getValueColumn()}]);
        } elseif ($metricTypeA instanceof CalculatedMetricType) {
            $collectionA = $athlete->calculatedMetrics()
                ->where('type', $metricTypeA->value)
                ->where('date', '>=', $startDate)
                ->orderBy('date')
                ->get()->map(fn ($m) => (object) ['date' => $m->date->toDateString(), 'value' => $m->value]);
        }

        $collectionB = null;
        if ($metricTypeB instanceof MetricType) {
            $collectionB = $athlete->metrics()
                ->where('metric_type', $metricTypeB->value)
                ->where('date', '>=', $startDate)
                ->orderBy('date')
                ->get()->map(fn ($m) => (object) ['date' => $m->date->toDateString(), 'value' => $m->{$metricTypeB->getValueColumn()}]);
        } elseif ($metricTypeB instanceof CalculatedMetricType) {
            $collectionB = $athlete->calculatedMetrics()
                ->where('type', $metricTypeB->value)
                ->where('date', '>=', $startDate)
                ->orderBy('date')
                ->get()->map(fn ($m) => (object) ['date' => $m->date->toDateString(), 'value' => $m->value]);
        }

        if ($collectionA === null || $collectionB === null) {
            return ['correlation' => null, 'impact_size' => null, 'reason' => 'Type de métrique non supporté.'];
        }

        return $this->calculateCorrelationFromCollections($collectionA, $collectionB);
    }

    /**
     * Calcule la corrélation de Pearson entre deux collections de données.
     */
    public function calculateCorrelationFromCollections(Collection $collectionA, Collection $collectionB): array
    {
        $valuesA = $collectionA->keyBy('date');
        $valuesB = $collectionB->keyBy('date');

        $commonDates = $valuesA->keys()->intersect($valuesB->keys());

        if ($commonDates->count() < 5) {
            return ['correlation' => null, 'impact_size' => null, 'reason' => 'Pas assez de points de données communs (min 5).'];
        }

        $vecA = $commonDates->map(fn ($date) => $valuesA[$date]->value)->values();
        $vecB = $commonDates->map(fn ($date) => $valuesB[$date]->value)->values();

        $n = $commonDates->count();
        $sumA = $vecA->sum();
        $sumB = $vecB->sum();
        $sumASq = $vecA->map(fn ($v) => $v * $v)->sum();
        $sumBSq = $vecB->map(fn ($v) => $v * $v)->sum();
        $pSum = $commonDates->map(fn ($date) => $valuesA[$date]->value * $valuesB[$date]->value)->sum();

        $num = $pSum - ($sumA * $sumB / $n);
        $den = sqrt(($sumASq - pow($sumA, 2) / $n) * ($sumBSq - pow($sumB, 2) / $n));

        if ($den == 0) {
            return ['correlation' => 0, 'impact_size' => 0, 'reason' => 'Dénominateur nul, corrélation nulle.'];
        }

        $correlation = $num / $den;
        $slope = ($den > 0) ? $num / ($sumASq - pow($sumA, 2) / $n) : 0;

        return [
            'correlation' => round($correlation, 2),
            'impact_size' => round($slope, 2),
        ];
    }
}
