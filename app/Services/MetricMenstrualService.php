<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Athlete;
use App\Enums\MetricType;
use Illuminate\Support\Collection;

class MetricMenstrualService
{
    protected MetricCalculationService $metricCalculationService;

    protected MetricTrendsService $metricTrendsService;

    protected MetricReadinessService $metricReadinessService;

    public function __construct(MetricCalculationService $metricCalculationService, MetricTrendsService $metricTrendsService, MetricReadinessService $metricReadinessService)
    {
        $this->metricCalculationService = $metricCalculationService;
        $this->metricTrendsService = $metricTrendsService;
        $this->metricReadinessService = $metricReadinessService;
    }

    private const ALERT_THRESHOLDS = [
        'MENSTRUAL_CYCLE' => [
            'amenorrhea_days_beyond_avg'    => 60,
            'oligomenorrhea_min_cycle'      => 21,
            'oligomenorrhea_max_cycle'      => 35,
            'delayed_cycle_days_beyond_avg' => 2,
            'prolonged_absence_no_avg'      => 45,
            'menstrual_fatigue_min'         => 7,
            'menstrual_perf_feel_max'       => 4,
        ],
    ];

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
     * Compare la moyenne d'une métrique donnée entre deux phases du cycle menstruel.
     *
     * @param string $phaseA Phase de comparaison (ex: 'Lutéale')
     * @param string $phaseB Phase de référence (ex: 'Folliculaire')
     * @param int $daysToAnalyze Nombre de jours à analyser en arrière.
     * @return array Résultat de la comparaison.
     */
    public function compareMetricAcrossPhases(
        Athlete $athlete,
        MetricType $metricType,
        string $phaseA = 'Lutéale',
        string $phaseB = 'Folliculaire',
        int $daysToAnalyze = 90
    ): array {
        // 1. Récupérer l'historique des cycles (J1) sur la dernière année pour être robuste
        $j1Metrics = $athlete->metrics()
            ->where('metric_type', MetricType::MORNING_FIRST_DAY_PERIOD->value)
            ->where('value', 1)
            ->where('date', '>=', Carbon::now()->subYear())
            ->orderBy('date', 'asc')
            ->get();

        if ($j1Metrics->count() < 2) {
            return ['impact' => 'n/a', 'reason' => 'Données de cycle insuffisantes (moins de 2 J1).'];
        }

        // 2. Récupérer les métriques à analyser sur la période voulue
        $metricsToAnalyze = $athlete->metrics()
            ->where('metric_type', $metricType->value)
            ->where('date', '>=', Carbon::now()->subDays($daysToAnalyze))
            ->orderBy('date', 'asc')
            ->get();

        $metricsByPhase = [$phaseA => [], $phaseB => []];

        // 3. Attribuer chaque métrique à une phase
        foreach ($metricsToAnalyze as $metric) {
            $metricDate = Carbon::parse($metric->date);
            
            // Trouver le J1 qui précède ou correspond à la date de la métrique
            $previousJ1 = $j1Metrics->last(fn ($j1) => Carbon::parse($j1->date) <= $metricDate);
            if (!$previousJ1) continue;

            // Trouver le J1 qui suit
            $nextJ1 = $j1Metrics->first(fn ($j1) => Carbon::parse($j1->date) > Carbon::parse($previousJ1->date));
            if (!$nextJ1) continue;

            $cycleLength = Carbon::parse($nextJ1->date)->diffInDays(Carbon::parse($previousJ1->date));
            $dayInCycle = $metricDate->diffInDays(Carbon::parse($previousJ1->date)) + 1;

            // Déterminer la phase (logique simplifiée de `deduceMenstrualCyclePhase`)
            $phase = 'Inconnue';
            if ($dayInCycle <= 5) $phase = 'Menstruelle';
            elseif ($dayInCycle > 5 && $dayInCycle < ($cycleLength / 2)) $phase = 'Folliculaire';
            elseif ($dayInCycle >= ($cycleLength / 2) && $dayInCycle <= ($cycleLength / 2) + 2) $phase = 'Ovulatoire';
            elseif ($dayInCycle > ($cycleLength / 2) + 2) $phase = 'Lutéale';

            if (array_key_exists($phase, $metricsByPhase)) {
                $metricsByPhase[$phase][] = $metric->value;
            }
        }

        // 4. Calculer les moyennes et comparer
        $avgA = !empty($metricsByPhase[$phaseA]) ? array_sum($metricsByPhase[$phaseA]) / count($metricsByPhase[$phaseA]) : null;
        $avgB = !empty($metricsByPhase[$phaseB]) ? array_sum($metricsByPhase[$phaseB]) / count($metricsByPhase[$phaseB]) : null;

        if ($avgA === null || $avgB === null) {
            return ['impact' => 'n/a', 'reason' => "Pas assez de données pour la phase {$phaseA} ou {$phaseB}."];
        }

        $difference = $avgA - $avgB;
        $impact = 'stable';
        // Seuil de 10% de la moyenne de référence pour être significatif
        if ($difference > ($avgB * 0.1)) $impact = 'higher'; 
        if ($difference < -($avgB * 0.1)) $impact = 'lower';

        return [
            'impact' => $impact,
            'difference' => round(abs($difference), 1),
            'avg_phase_a' => round($avgA, 1),
            'avg_phase_b' => round($avgB, 1),
            'phase_a' => $phaseA,
            'phase_b' => $phaseB,
        ];
    }
}
