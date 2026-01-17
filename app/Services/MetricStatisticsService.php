<?php

namespace App\Services;

use Carbon\Carbon;
use App\Enums\MetricType;
use App\Models\TrainingPlanWeek;
use Illuminate\Support\Collection;

class MetricStatisticsService
{
    /**
     * Calcule le Score de Bien-être Matinal (SBM) pour une collection de métriques.
     * Somme (Qualité du sommeil + (10 - Fatigue) + (10 - Douleur) + Humeur)
     */
    public function calculateSbmForCollection(Collection $dailyMetrics): ?float
    {
        $sbmSum = 0;
        $maxPossibleSbm = 0;

        $metrics = [
            MetricType::MORNING_SLEEP_QUALITY->value   => 'positive',
            MetricType::MORNING_GENERAL_FATIGUE->value => 'negative',
            MetricType::MORNING_PAIN->value            => 'negative',
            MetricType::MORNING_MOOD_WELLBEING->value  => 'positive',
        ];

        foreach ($metrics as $type => $polarity) {
            $metric = $dailyMetrics->firstWhere('metric_type', $type);
            if ($metric !== null && is_numeric($metric->value)) {
                $value = (float) $metric->value;
                $sbmSum += ($polarity === 'positive') ? $value : (10 - $value);
                $maxPossibleSbm += 10;
            }
        }

        if ($maxPossibleSbm === 0) {
            return null;
        }

        // Si des données sont manquantes, on normalise sur 40 points
        return (float) ($sbmSum / $maxPossibleSbm) * 40;
    }

    /**
     * Calcule la date de début en fonction de la période demandée.
     */
    public function calculatePeriodStartDate(string $period): Carbon
    {
        return match ($period) {
            'last_7_days'  => now()->subDays(7)->startOfDay(),
            'last_30_days' => now()->subDays(30)->startOfDay(),
            'all_time'     => Carbon::createFromTimestamp(0),
            default        => now()->subDays(7)->startOfDay(),
        };
    }

    /**
     * Calcule la Charge Planifiée Hebdomadaire (CPH).
     * CPH = Volume * (Intensité / 10)
     */
    public function calculateCph(TrainingPlanWeek $planWeek): float
    {
        if ($planWeek->volume_planned === null || $planWeek->intensity_planned === null) {
            return 0.0;
        }

        return (float) ($planWeek->volume_planned * ($planWeek->intensity_planned / 10));
    }

    /**
     * Formate la valeur d'une métrique pour l'affichage.
     */
    public function formatMetricDisplayValue(mixed $value, MetricType $type): string
    {
        if ($value === null) {
            return 'n/a';
        }

        if ($type->getValueColumn() === 'note') {
            return (string) $value;
        }

        if (in_array($type, [
            MetricType::MORNING_SLEEP_QUALITY,
            MetricType::MORNING_GENERAL_FATIGUE,
            MetricType::MORNING_PAIN,
            MetricType::MORNING_MOOD_WELLBEING,
        ])) {
            return is_numeric($value) ? round($value, 0).'/10' : (string) $value;
        }

        $unit = $type->getUnit();

        return $value.($unit ? ' '.$unit : '');
    }

    /**
     * Prépare les données pour un graphique d'une seule métrique.
     */
    public function prepareSingleMetricChartData(Collection $metrics, MetricType $type): array
    {
        $sorted = $metrics->sortBy('date');

        $labels = $sorted->pluck('date')->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : $d)->values()->toArray();
        $data = $sorted->pluck('value')->map(fn ($v) => (float) $v)->values()->toArray();

        return [
            'labels'          => $labels,
            'data'            => $data,
            'labels_and_data' => $sorted->map(fn ($m) => [
                'date'  => $m->date instanceof Carbon ? $m->date->toDateString() : $m->date,
                'value' => (float) $m->value,
            ])->values()->toArray(),
            'unit'  => $type->getUnit(),
            'label' => $type->getLabel(),
        ];
    }

    /**
     * Calcule la tendance pour une collection de données numériques.
     */
    public function calculateGenericNumericTrend(Collection $dataCollection): array
    {
        $numericData = $dataCollection->filter(fn ($item) => is_numeric($item->value ?? null))
            ->sortBy(fn ($item) => $item->date);

        if ($numericData->count() < 2) {
            return ['trend' => 'n/a', 'change' => null];
        }

        $firstValue = (float) $numericData->first()->value;
        $lastValue = (float) $numericData->last()->value;

        if ($firstValue == $lastValue) {
            return ['trend' => 'stable', 'change' => 0];
        }

        $trend = $lastValue > $firstValue ? 'increasing' : 'decreasing';
        $change = $firstValue != 0 ? (($lastValue - $firstValue) / abs($firstValue)) * 100 : 100;

        return [
            'trend'  => $trend,
            'change' => round($change, 1),
        ];
    }
}
