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
     * Vérifie si l'athlète a rempli ses métriques mensuelles pour le mois donné.
     * Inclut par défaut la charge mentale et la motivation, et le poids si activé.
     */
    public function hasFilledMonthlyMetric(Athlete $athlete, ?Carbon $date = null): bool
    {
        $date = $date ?? now();

        $requiredMetricTypes = [
            MetricType::MONTHLY_MENTAL_LOAD->value,
            MetricType::MONTHLY_MOTIVATION->value,
        ];

        if ($athlete->getPreference('track_monthly_weight', true)) {
            $requiredMetricTypes[] = MetricType::MORNING_BODY_WEIGHT_KG->value;
        }

        $filledCount = Metric::query()
            ->where('athlete_id', $athlete->id)
            ->whereIn('metric_type', $requiredMetricTypes)
            ->whereMonth('date', $date->month)
            ->whereYear('date', $date->year)
            ->count();

        return $filledCount >= count($requiredMetricTypes);
    }

    /**
     * Détermine si on doit afficher le rappel pour la métrique mensuelle.
     * Affiche le rappel tout le mois tant que tout n'est pas rempli.
     */
    public function shouldShowMonthlyMetricAlert(Athlete $athlete): bool
    {
        return ! $this->hasFilledMonthlyMetric($athlete);
    }

    /**
     * Récupère tous les athlètes qui n'ont pas encore rempli toutes leurs métriques mensuelles.
     */
    public function getAthletesNeedingMonthlyReminder(?Carbon $date = null): \Illuminate\Support\Collection
    {
        $date = $date ?? now();

        // On récupère tous les athlètes et on filtre en PHP car la liste des métriques requises dépend des préférences de chacun
        return Athlete::all()->filter(fn ($athlete) => ! $this->hasFilledMonthlyMetric($athlete, $date));
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
