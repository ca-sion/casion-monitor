<?php

namespace App\Services;

use App\Models\Metric;
use App\Models\Athlete;
use App\Enums\MetricType;
use Illuminate\Support\Carbon;

class ReminderService
{
    public function __construct(
        protected MetricMenstrualService $menstrualService
    ) {}

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
        if (! $athlete->getPreference('track_monthly_weight', true)) {
            return false;
        }

        return ! $this->hasFilledMonthlyMetric($athlete);
    }

    /**
     * Récupère tous les athlètes qui n'ont pas encore rempli leur métrique mensuelle.
     */
    public function getAthletesNeedingMonthlyReminder(?Carbon $date = null): \Illuminate\Support\Collection
    {
        $date = $date ?? now();

        return Athlete::all()->filter(function ($athlete) use ($date) {
            if (! $athlete->getPreference('track_monthly_weight', true)) {
                return false;
            }

            return ! $this->hasFilledMonthlyMetric($athlete, $date);
        });
    }

    /**
     * Détermine le statut de rappel du cycle menstruel pour une athlète.
     * Retourne un tableau avec le type de rappel et des infos complémentaires.
     */
    public function getMenstrualReminderStatus(Athlete $athlete): ?array
    {
        if ($athlete->gender->value !== 'w') {
            return null;
        }

        $cycleData = $this->menstrualService->deduceMenstrualCyclePhase($athlete);
        $phase = $cycleData['phase'];
        $daysInPhase = $cycleData['days_in_phase'];
        $avg = $cycleData['cycle_length_avg'];

        // Cas 1 : Données manquantes pour établir une prédiction
        if ($phase === 'Inconnue' && str_contains($cycleData['reason'], 'deux J1')) {
            return [
                'type'    => 'MISSING_DATA',
                'message' => 'Configurez votre suivi de cycle pour bénéficier des conseils d\'entraînement personnalisés.',
                'color'   => 'sky',
            ];
        }

        // Cas 2 : Déjà en cours de règles (ou J1 saisi récemment) -> Pas de rappel
        if ($phase === 'Menstruelle') {
            return null;
        }

        // Cas 3 : Retard ou oubli de saisie
        if ($phase === 'Potentiel retard ou cycle long') {
            return [
                'type'    => 'OVERDUE',
                'message' => 'Votre J1 semble avoir du retard. N\'oubliez pas de le saisir dès qu\'il arrive.',
                'color'   => 'amber',
            ];
        }

        // Cas 4 : Anticipation (Fenêtre J1 proche)
        if ($avg && $daysInPhase >= ($avg - 2)) {
            return [
                'type'    => 'EXPECTED',
                'message' => 'Votre nouveau cycle devrait bientôt commencer. Prête à noter votre J1 ?',
                'color'   => 'purple',
            ];
        }

        return null;
    }

    /**
     * Récupère les athlètes qui doivent recevoir une notification de rappel J1 aujourd'hui.
     */
    public function getAthletesNeedingMenstrualNotification(): \Illuminate\Support\Collection
    {
        return Athlete::where('gender', 'w')->get()->filter(function ($athlete) {
            $status = $this->getMenstrualReminderStatus($athlete);

            // On ne notifie que pour le retard ou le jour prévu
            return $status && in_array($status['type'], ['OVERDUE', 'EXPECTED']);
        });
    }
}
