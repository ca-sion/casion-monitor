<?php

namespace App\Services;

use App\Models\Metric;
use App\Models\Athlete;
use App\Enums\MetricType;
use Illuminate\Support\Carbon;

class ReminderService
{
    /**
     * Vérifie si l'athlète a rempli sa métrique mensuelle pour le mois donné.
     * La métrique de référence est le poids corporel (MORNING_BODY_WEIGHT_KG).
     */
    public function hasFilledMonthlyMetric(Athlete $athlete, ?Carbon $date = null): bool
    {
        $date = $date ?? now();

        return Metric::query()
            ->where('athlete_id', $athlete->id)
            ->where('metric_type', MetricType::MORNING_BODY_WEIGHT_KG->value)
            ->whereMonth('date', $date->month)
            ->whereYear('date', $date->year)
            ->exists();
    }

    /**
     * Détermine si on doit afficher le rappel pour la métrique mensuelle.
     * Affiche le rappel tout le mois tant que ce n'est pas fait.
     */
    public function shouldShowMonthlyMetricAlert(Athlete $athlete): bool
    {
        return ! $this->hasFilledMonthlyMetric($athlete);
    }

    /**
     * Récupère tous les athlètes qui n'ont pas encore rempli leur métrique mensuelle.
     */
    public function getAthletesNeedingMonthlyReminder(?Carbon $date = null): \Illuminate\Support\Collection
    {
        $date = $date ?? now();

        return Athlete::whereDoesntHave('metrics', function ($query) use ($date) {
            $query->where('metric_type', MetricType::MORNING_BODY_WEIGHT_KG->value)
                ->whereMonth('date', $date->month)
                ->whereYear('date', $date->year);
        })->get();
    }
}
