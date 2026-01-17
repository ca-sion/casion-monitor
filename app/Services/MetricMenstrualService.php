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

        // Récupérer toutes les métriques J1 (Premier Jour des Règles) dans l'ordre chronologique inverse des deux dernières années
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
            $daysSinceLastPeriod = Carbon::now()->startOfDay()->diffInDays($lastPeriodStart->startOfDay(), true);
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
            if ($j1Metrics->count() === 1 && $daysSinceLastPeriod <= 5) {
                $phase = 'Menstruelle';
                $reason = 'Phase de réinitialisation hormonale (basée sur votre seul J1 enregistré).';
            } else {
                $phase = 'Inconnue';
                $reason = 'Enregistrez au moins deux J1 pour calculer la durée moyenne de votre cycle.';
            }
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
            $dayInCycle = $daysSinceLastPeriod + 1;

            // Phase Menstruelle (Jour 1 à 5)
            if ($dayInCycle <= 5) {
                $phase = 'Menstruelle';
                $reason = 'Phase de réinitialisation hormonale et gestion de l\'inflammation.';
            }
            // Phase Folliculaire (Jour 6 à Ovulation)
            elseif ($dayInCycle > 5 && $dayInCycle < ($averageCycleLength / 2)) {
                $phase = 'Folliculaire';
                $reason = 'Fenêtre de force : Capacité de récupération et tolérance à l\'intensité optimales.';
            }
            // Phase Ovulatoire (Fenêtre autour du milieu du cycle)
            elseif ($dayInCycle >= ($averageCycleLength / 2) && $dayInCycle <= ($averageCycleLength / 2) + 2) {
                $phase = 'Ovulatoire';
                $reason = 'Pic hormonal : Performance maximale, mais vigilance accrue sur la stabilité articulaire.';
            }
            // Phase Lutéale (Post-ovulation jusqu'à fin du cycle)
            elseif ($dayInCycle > ($averageCycleLength / 2) + 2) {
                $phase = 'Lutéale';
                $reason = 'Phase métabolique : Température centrale plus élevée et utilisation accrue des graisses.';
            }
            // Cas de secours
            else {
                $phase = 'Inconnue';
                $reason = 'Impossible de déterminer la phase du cycle normal avec les données actuelles.';
            }
        }

        return [
            'phase'             => $phase,
            'reason'            => $reason,
            'days_in_phase'     => $daysSinceLastPeriod !== null ? $daysSinceLastPeriod + 1 : null,
            'cycle_length_avg'  => $averageCycleLength !== null ? round($averageCycleLength) : null,
            'last_period_start' => $lastPeriodStart ? $lastPeriodStart->format('d.m.Y') : null,
            'last_period_date'  => $lastPeriodStart,
        ];
    }

    /**
     * Compare la moyenne d'une métrique donnée entre deux phases du cycle menstruel.
     *
     * @param  string  $phaseA  Phase de comparaison (ex: 'Lutéale')
     * @param  string  $phaseB  Phase de référence (ex: 'Folliculaire')
     * @param  int  $daysToAnalyze  Nombre de jours à analyser en arrière.
     * @param  Carbon|null  $endDate  Date de fin de l'analyse (par défaut aujourd'hui).
     * @return array Résultat de la comparaison.
     */
    public function compareMetricAcrossPhases(
        Athlete $athlete,
        MetricType $metricType,
        string $phaseA = 'Lutéale',
        string $phaseB = 'Folliculaire',
        int $daysToAnalyze = 90,
        ?Carbon $endDate = null
    ): array {
        $endDate = $endDate ?? Carbon::now();
        $startDate = $endDate->copy()->subDays($daysToAnalyze);

        // 1. Récupérer l'historique des cycles (J1) sur une période large pour couvrir les métriques
        $j1Metrics = $athlete->metrics()
            ->where('metric_type', MetricType::MORNING_FIRST_DAY_PERIOD->value)
            ->where('value', 1)
            ->where('date', '<=', $endDate)
            ->orderBy('date', 'asc')
            ->get();

        if ($j1Metrics->isEmpty()) {
            return ['impact' => 'n/a', 'reason' => 'Aucune donnée de cycle (J1) enregistrée.'];
        }

        // Calculer la moyenne du cycle pour les estimations
        $averageCycleLength = 28; // Valeur par défaut
        if ($j1Metrics->count() >= 2) {
            $cycleLengths = [];
            for ($i = 0; $i < $j1Metrics->count() - 1; $i++) {
                $cycleLengths[] = Carbon::parse($j1Metrics[$i + 1]->date)->diffInDays(Carbon::parse($j1Metrics[$i]->date), true);
            }
            $averageCycleLength = array_sum($cycleLengths) / count($cycleLengths);
        }

        // 2. Récupérer les métriques à analyser
        $metricsToAnalyze = $athlete->metrics()
            ->where('metric_type', $metricType->value)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date', 'asc')
            ->get();

        $metricsByPhase = [$phaseA => [], $phaseB => []];

        // 3. Attribuer chaque métrique à une phase
        foreach ($metricsToAnalyze as $metric) {
            $metricDate = Carbon::parse($metric->date);

            // Trouver le J1 qui précède ou correspond à la date de la métrique
            $previousJ1 = $j1Metrics->last(fn ($j1) => Carbon::parse($j1->date) <= $metricDate);
            if (! $previousJ1) {
                continue;
            }

            // Trouver le J1 qui suit pour connaître la longueur réelle du cycle
            $nextJ1 = $j1Metrics->first(fn ($j1) => Carbon::parse($j1->date) > Carbon::parse($previousJ1->date));
            $cycleLength = $nextJ1
                ? Carbon::parse($nextJ1->date)->diffInDays(Carbon::parse($previousJ1->date))
                : $averageCycleLength;

            $dayInCycle = $metricDate->startOfDay()->diffInDays(Carbon::parse($previousJ1->date)->startOfDay(), true) + 1;

            // Déterminer la phase
            $phase = 'Inconnue';
            if ($dayInCycle <= 5) {
                $phase = 'Menstruelle';
            } elseif ($dayInCycle > 5 && $dayInCycle < ($cycleLength / 2)) {
                $phase = 'Folliculaire';
            } elseif ($dayInCycle >= ($cycleLength / 2) && $dayInCycle <= ($cycleLength / 2) + 2) {
                $phase = 'Ovulatoire';
            } elseif ($dayInCycle > ($cycleLength / 2) + 2) {
                $phase = 'Lutéale';
            }

            if (array_key_exists($phase, $metricsByPhase)) {
                $metricsByPhase[$phase][] = $metric->value;
            }
        }

        // 4. Calculer les moyennes et comparer
        $avgA = ! empty($metricsByPhase[$phaseA]) ? array_sum($metricsByPhase[$phaseA]) / count($metricsByPhase[$phaseA]) : null;
        $avgB = ! empty($metricsByPhase[$phaseB]) ? array_sum($metricsByPhase[$phaseB]) / count($metricsByPhase[$phaseB]) : null;

        if ($avgA === null || $avgB === null) {
            return ['impact' => 'n/a', 'reason' => "Pas assez de données pour la phase {$phaseA} ou {$phaseB}."];
        }

        $difference = $avgA - $avgB;
        $impact = 'stable';
        // Seuil de 10% de la moyenne de référence pour être significatif
        if ($difference > ($avgB * 0.1)) {
            $impact = 'higher';
        }
        if ($difference < -($avgB * 0.1)) {
            $impact = 'lower';
        }

        return [
            'impact'      => $impact,
            'difference'  => round(abs($difference), 1),
            'avg_phase_a' => round($avgA, 1),
            'avg_phase_b' => round($avgB, 1),
            'phase_a'     => $phaseA,
            'phase_b'     => $phaseB,
        ];
    }

    /**
     * Analyse la tendance à long terme de l'impact d'une phase sur une métrique (ex: la Lutéale devient-elle plus pénible ?).
     * Compare l'écart entre Phase A et Phase B sur une période passée (P1) et une période récente (P2).
     *
     * @param  string  $phaseA  Phase de comparaison (ex: 'Lutéale')
     * @param  string  $phaseB  Phase de référence (ex: 'Folliculaire')
     * @return array Résultat de l'analyse de tendance.
     */
    public function getLongTermPhaseTrend(
        Athlete $athlete,
        MetricType $metricType,
        string $phaseA = 'Lutéale',
        string $phaseB = 'Folliculaire'
    ): array {
        $daysInPast = 90; // Durée de chaque période (P2 et P1 = 6 mois total)

        // Période 2 : Récente (T-90 à T-0)
        $recentAnalysis = $this->compareMetricAcrossPhases($athlete, $metricType, $phaseA, $phaseB, $daysInPast);

        // Période 1 : Ancienne (T-180 à T-90). La date de fin est il y a 90 jours.
        $pastEndDate = Carbon::now()->subDays($daysInPast);
        $pastAnalysis = $this->compareMetricAcrossPhases($athlete, $metricType, $phaseA, $phaseB, $daysInPast, $pastEndDate);

        // Si l'une des analyses est incomplète, on ne peut pas comparer la tendance.
        if ($recentAnalysis['impact'] === 'n/a' || $pastAnalysis['impact'] === 'n/a') {
            return ['trend' => 'n/a', 'reason' => 'Données insuffisantes pour l\'analyse longue durée (min 6 mois).'];
        }

        // Calculer l'écart Lutéale - Folliculaire pour chaque période (différence positive = A est supérieur à B)
        $diffRecent = $recentAnalysis['avg_phase_a'] - $recentAnalysis['avg_phase_b'];
        $diffPast = $pastAnalysis['avg_phase_a'] - $pastAnalysis['avg_phase_b'];

        $trend = 'stable';
        $change = $diffRecent - $diffPast; // Positif si l'écart s'est creusé (l'impact de A sur B est plus grand/pire)

        // Seuil de changement : 0.5 point sur l'échelle (ex: 1-10) pour être significatif
        if ($change > 0.5) {
            $trend = 'worsening'; // Aggravation de l'écart
        } elseif ($change < -0.5) {
            $trend = 'improving'; // Amélioration de l'écart
        }

        return [
            'trend'       => $trend,
            'change'      => round(abs($change), 1),
            'recent_diff' => round($diffRecent, 1),
            'past_diff'   => round($diffPast, 1),
            'reason'      => $trend === 'worsening' ? "L'écart entre la phase {$phaseA} et la phase {$phaseB} s'est aggravé de ".round($change, 1).' points.' : null,
        ];
    }

    /**
     * Fournit une recommandation d'adaptation de la charge d'entraînement en fonction de la phase actuelle.
     * C'est le "Call to Action" du rapport.
     *
     * @return array Recommandation claire (action, justification, status).
     */
    public function getPhaseSpecificRecommendation(Athlete $athlete, string $currentPhase): array
    {
        // On vérifie l'impact global de la phase Lutéale sur la fatigue pour affiner les recommandations sensibles.
        $fatigueImpact = $this->compareMetricAcrossPhases(
            $athlete,
            MetricType::MORNING_GENERAL_FATIGUE,
            'Lutéale',
            'Folliculaire'
        );

        $isLutealFatigueHigh = $fatigueImpact['impact'] === 'higher';

        // Logique de recommandation (inspirée de la littérature sportive)
        switch ($currentPhase) {
            case 'Menstruelle':
                return [
                    'action'        => 'Tranquille',
                    'justification' => "L'objectif est la gestion de l'inflammation et de la douleur. C'est la phase idéale pour la récupération active et le travail technique à faible intensité.",
                    'status'        => 'easy',
                ];
            case 'Folliculaire':
                return [
                    'action'        => 'GO',
                    'justification' => "Taux d'oestrogènes en hausse = tolérance à la douleur et récupération accrues. Fenêtre idéale pour les charges lourdes, les séances de pic et les tests de force maximale.",
                    'status'        => 'optimal',
                ];
            case 'Ovulatoire':
                return [
                    'action'        => 'GO',
                    'justification' => "Pic de performance générale. Attention toutefois à la potentielle laxité ligamentaire accrue lors de mouvements brusques ou changements de direction.",
                    'status'        => 'optimal',
                ];
            case 'Lutéale':
                $action = $isLutealFatigueHigh ? 'Tranquille' : 'Modéré';
                $justification = $isLutealFatigueHigh ?
                    "Votre fatigue est significativement plus haute dans cette phase. **Il est conseillé de lever le pied** et de réduire le volume d'entraînement de 10-20% pour minimiser le risque de surentraînement." :
                    "Votre corps gère bien cette phase. Maintenez une charge modérée en privilégiant l'endurance. Si la fatigue du jour est élevée, rester tranquille.";
                $status = $isLutealFatigueHigh ? 'warning' : 'moderate';

                return [
                    'action'        => $action,
                    'justification' => $justification,
                    'status'        => $status,
                ];
            case 'Aménorrhée':
            case 'Oligoménorrhée':
                return [
                    'action'        => 'STOP! (Alerte santé)',
                    'justification' => "Arrêtez les charges d'entraînement intenses et consultez un spécialiste. Votre cycle indique un déséquilibre potentiellement lié à un déficit énergétique (RED-S) ou un stress excessif.",
                    'status'        => 'critical',
                ];
            default:
                return [
                    'action'        => 'Données manquantes',
                    'justification' => 'Renseignez vos dates de règles (J1) pour une analyse personnalisée et des recommandations fiables.',
                    'status'        => 'neutral',
                ];
        }
    }
}
