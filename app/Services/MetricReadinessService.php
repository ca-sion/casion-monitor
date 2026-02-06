<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Athlete;
use App\Enums\MetricType;
use App\Models\CalculatedMetric;
use Illuminate\Support\Collection;
use App\Enums\CalculatedMetricType;

class MetricReadinessService
{
    protected MetricCalculationService $metricCalculationService;

    protected MetricTrendsService $metricTrendsService;

    protected array $readinessDetails;

    public function __construct(MetricCalculationService $metricCalculationService, MetricTrendsService $metricTrendsService)
    {
        $this->metricCalculationService = $metricCalculationService;
        $this->metricTrendsService = $metricTrendsService;
    }

    private const ALERT_THRESHOLDS = [
        'READINESS_SCORE' => [
            'sbm_penalty_factor'             => 5,
            'hrv_drop_severe_percent'        => -10,
            'hrv_drop_moderate_percent'      => -5,
            'pain_penalty_factor'            => 4,
            'pre_session_energy_low'         => 4,
            'pre_session_energy_medium'      => 6,
            'pre_session_leg_feel_low'       => 4,
            'pre_session_leg_feel_medium'    => 6,
            'pre_session_penalty_high'       => 15,
            'pre_session_penalty_medium'     => 5,
            'charge_overload_penalty_factor' => 15,
            'charge_overload_threshold'      => 1.3,
            'level_red_threshold'            => 50,
            'level_orange_threshold'         => 70,
            'level_yellow_threshold'         => 85,
            'severe_pain_threshold'          => 7,
            'menstrual_energy_low'           => 4,
        ],
    ];

    public const ESSENTIAL_DAILY_READINESS_METRICS = [
        MetricType::MORNING_SLEEP_QUALITY,
        MetricType::MORNING_GENERAL_FATIGUE,
        MetricType::PRE_SESSION_ENERGY_LEVEL,
        MetricType::PRE_SESSION_LEG_FEEL,
        MetricType::MORNING_HRV,
        MetricType::MORNING_PAIN,
    ];

    public const ALL_READINESS_METRICS = [
        MetricType::MORNING_HRV,
        MetricType::MORNING_SLEEP_QUALITY,
        MetricType::MORNING_SLEEP_DURATION,
        MetricType::MORNING_GENERAL_FATIGUE,
        MetricType::MORNING_PAIN,
        MetricType::MORNING_MOOD_WELLBEING,
        MetricType::PRE_SESSION_ENERGY_LEVEL,
        MetricType::PRE_SESSION_LEG_FEEL,
    ];

    /**
     * Calcule le score global de readiness de l'athlète en prenant en compte divers facteurs.
     * Retourne le score final et un tableau détaillé des piliers.
     *
     * @param  Athlete  $athlete  L'athlète pour lequel calculer le score.
     * @param  Collection  $allMetrics  Toutes les métriques disponibles pour l'athlète.
     * @param  Carbon|null  $targetDate  La date pour laquelle calculer le score (défaut: aujourd'hui).
     * @return array Un tableau contenant 'readiness_score' (int) et 'readiness_details' (array).
     */
    public function calculateOverallReadinessScore(Athlete $athlete, Collection $allMetrics, ?Carbon $targetDate = null): array
    {
        $this->readinessDetails = [];
        $today = $targetDate ? $targetDate->copy()->startOfDay() : now()->startOfDay();

        // Calcul de l'indice de confiance basé sur le nombre de métriques remplies (sur 8 possibles)
        $metricsCount = 0;
        foreach (self::ALL_READINESS_METRICS as $metricType) {
            if ($allMetrics->where('metric_type', $metricType->value)->where('date', $today)->isNotEmpty()) {
                $metricsCount++;
            }
        }
        $confidenceIndex = (int) round(($metricsCount / count(self::ALL_READINESS_METRICS)) * 100);

        $pillars = [
            'physio' => [
                'label'  => 'Physiologique (VFC)',
                'weight' => 0.25,
                'type'   => MetricType::MORNING_HRV,
                'value'  => $this->getPhysioScore($allMetrics, $today),
            ],
            'subjective' => [
                'label'  => 'Subjectif (SBM)',
                'weight' => 0.35,
                'type'   => CalculatedMetricType::SBM,
                'value'  => $this->getSubjectiveScore($athlete, $today),
            ],
            'immediate' => [
                'label'  => 'Immédiat (Énergie/Jambes)',
                'weight' => 0.40,
                'type'   => MetricType::PRE_SESSION_ENERGY_LEVEL, // On utilise l'énergie comme proxy pour le pilier
                'value'  => $this->getImmediateScore($allMetrics, $today),
            ],
        ];

        // 1. Redistribution dynamique des poids si données manquantes
        $totalWeightAvailable = 0;
        foreach ($pillars as $pillar) {
            if ($pillar['value'] !== null) {
                $totalWeightAvailable += $pillar['weight'];
            }
        }

        if ($totalWeightAvailable === 0) {
            return [
                'readiness_score'   => null,
                'readiness_details' => [],
                'confidence_index'  => 0,
            ];
        }

        $finalScore = 0;
        foreach ($pillars as $key => $pillar) {
            if ($pillar['value'] !== null) {
                $adjustedWeight = $pillar['weight'] / $totalWeightAvailable;
                $contribution = $pillar['value'] * $adjustedWeight;
                $finalScore += $contribution;

                $this->addReadinessDetail(
                    $pillar['type'],
                    $contribution,
                    (int) round($finalScore),
                    $pillar['value'],
                    ['label' => $pillar['label'], 'weight' => $pillar['weight'], 'adjusted_weight' => $adjustedWeight]
                );
            }
        }

        // 2. Application du Safety Cap (Veto) - Douleur, Ratio de charge, etc.
        $finalScore = $this->applySafetyCaps($finalScore, $athlete, $allMetrics, $today);

        return [
            'readiness_score'   => (int) round($finalScore),
            'readiness_details' => $this->readinessDetails,
            'confidence_index'  => $confidenceIndex,
        ];
    }

    /**
     * Calcule le score physiologique basé sur la VFC (HRV).
     * Compare la valeur du jour à la moyenne des 7 derniers jours.
     */
    protected function getPhysioScore(Collection $allMetrics, Carbon $today): ?float
    {
        $hrvMetrics = $allMetrics->where('metric_type', MetricType::MORNING_HRV->value)->sortByDesc('date');
        $todayHrv = $hrvMetrics->where('date', $today)->first()?->value;

        if ($todayHrv === null) {
            return null;
        }

        $hrv7DayAvg = $hrvMetrics->where('date', '>=', $today->copy()->subDays(7))
            ->where('date', '<', $today)
            ->avg('value');

        if (! $hrv7DayAvg || $hrv7DayAvg <= 0) {
            return 100; // Pas de base de comparaison, on assume 100%
        }

        $changePercent = (($todayHrv - $hrv7DayAvg) / $hrv7DayAvg) * 100;

        // Scoring : 0% changement = 100pts. -20% changement = 0pts.
        // On pénalise les baisses plus que les hausses.
        if ($changePercent >= 0) {
            return 100; // Une hausse est généralement signe de bonne récup
        }

        return max(0, min(100, 100 + ($changePercent * 5))); // -20% * 5 = -100pts
    }

    /**
     * Calcule le score subjectif basé sur le SBM (Sommeil, Fatigue, Humeur, Douleur).
     */
    protected function getSubjectiveScore(Athlete $athlete, Carbon $today): ?float
    {
        $dailySbm = CalculatedMetric::where('athlete_id', $athlete->id)
            ->where('date', $today)
            ->where('type', CalculatedMetricType::SBM)
            ->value('value');

        if ($dailySbm === null) {
            return null;
        }

        // SBM est sur 10, on normalise sur 100.
        return $dailySbm * 10;
    }

    /**
     * Calcule le score d'état immédiat (énergie et sensations de jambes avant session).
     */
    protected function getImmediateScore(Collection $allMetrics, Carbon $today): ?float
    {
        $energy = $allMetrics->where('metric_type', MetricType::PRE_SESSION_ENERGY_LEVEL->value)
            ->where('date', $today)
            ->first()?->value;

        $legs = $allMetrics->where('metric_type', MetricType::PRE_SESSION_LEG_FEEL->value)
            ->where('date', $today)
            ->first()?->value;

        if ($energy === null && $legs === null) {
            return null;
        }

        $values = collect([$energy, $legs])->filter(fn ($v) => $v !== null);

        // Moyenne des deux, normalisée sur 100 (les deux sont sur 10).
        return $values->avg() * 10;
    }

    /**
     * Applique des limites de sécurité (Safety Caps) basées sur des alertes critiques.
     */
    protected function applySafetyCaps(float $currentScore, Athlete $athlete, Collection $allMetrics, Carbon $today): float
    {
        // Cap 1 : Douleur sévère (Veto)
        $morningPain = $allMetrics->where('metric_type', MetricType::MORNING_PAIN->value)
            ->where('date', $today)
            ->first()?->value;

        if ($morningPain !== null && $morningPain >= self::ALERT_THRESHOLDS['READINESS_SCORE']['severe_pain_threshold']) {
            $currentScore = min($currentScore, 40); // Cap à 40 max
            $this->addReadinessDetail(MetricType::MORNING_PAIN, 0, (int) round($currentScore), $morningPain, ['cap_applied' => true, 'message' => 'Veto Douleur Sévère']);
        }

        // Cap 2 : Surcharge de charge (Ratio CIH/CPH)
        $chargeRatio = CalculatedMetric::where('athlete_id', $athlete->id)
            ->where('date', $today)
            ->where('type', CalculatedMetricType::RATIO_CIH_NORMALIZED_CPH)
            ->value('value');

        $overloadThreshold = self::ALERT_THRESHOLDS['READINESS_SCORE']['charge_overload_threshold'];
        if ($chargeRatio !== null && $chargeRatio > $overloadThreshold) {
            // Si ratio > 1.3, on réduit le score drastiquement
            $penalty = ($chargeRatio - $overloadThreshold) * 50; // ex: 1.5 ratio -> (0.2) * 50 = 10pts de cap
            $currentScore = max(0, $currentScore - $penalty);
            $this->addReadinessDetail(CalculatedMetricType::RATIO_CIH_CPH, $penalty, (int) round($currentScore), $chargeRatio, ['cap_applied' => true, 'message' => 'Surcharge Charge']);
        }

        return $currentScore;
    }

    /**
     * Récupère le statut de readiness de l'athlète, incluant le score, le niveau, le message,
     * la recommandation, les alertes et le détail du calcul du score.
     *
     * @param  Athlete  $athlete  L'athlète pour lequel récupérer le statut.
     * @param  Collection  $allMetrics  Toutes les métriques disponibles pour l'athlète.
     * @param  Carbon|null  $targetDate  La date pour laquelle récupérer le statut (défaut: aujourd'hui).
     * @return array Un tableau associatif contenant le statut complet de readiness.
     */
    public function getAthleteReadinessStatus(Athlete $athlete, Collection $allMetrics, ?Carbon $targetDate = null): array
    {
        $readinessScore = 100;
        $readinessThresholds = self::ALERT_THRESHOLDS['READINESS_SCORE'];
        $today = $targetDate ? $targetDate->copy()->startOfDay() : now()->startOfDay();

        $status = [
            'level'           => 'green',
            'message'         => "L'athlète est prêt pour l'entraînement !",
            'readiness_score' => null,
            'recommendation'  => "Poursuivre l'entraînement planifié.",
            'alerts'          => [],
            'details'         => [],
            'details_text'    => null,
        ];

        // Vérification des métriques quotidiennes manquantes
        $missingDailyMetricsData = $this->checkMissingDailyReadinessMetrics($allMetrics, $today);
        $status['alerts'] = $missingDailyMetricsData['alerts'];
        $missingCount = $missingDailyMetricsData['missing_count'];
        $missingMetricNames = $missingDailyMetricsData['missing_metric_names'];

        if ($missingCount > 3) {
            $missingNamesString = implode(', ', $missingMetricNames);
            $status['level'] = 'neutral';
            $status['message'] = 'Score non calculable.';
            $status['recommendation'] = "Trop de données essentielles sont manquantes pour aujourd'hui ({$missingCount} manquantes : {$missingNamesString}). Veuillez remplir toutes les métriques quotidiennes pour obtenir un score précis.";
            $status['readiness_score'] = 'n/a';
        } else {
            // Calcul du score global de readiness UNIQUEMENT si pas trop de données manquantes
            $readinessResult = $this->calculateOverallReadinessScore($athlete, $allMetrics, $today);
            $readinessScore = $readinessResult['readiness_score'];
            $readinessDetails = $readinessResult['readiness_details'];
            $confidenceIndex = $readinessResult['confidence_index'];

            $status['readiness_score'] = $readinessScore;
            $status['confidence_index'] = $confidenceIndex;

            if ($readinessScore === null) {
                $status['level'] = 'neutral';
                $status['message'] = 'Données insuffisantes.';
                $status['recommendation'] = 'Veuillez remplir les métriques quotidiennes pour calculer votre état de forme.';
            } else {
                // Définition des règles pour les niveaux de readiness basées sur le score
                if ($readinessScore < $readinessThresholds['level_red_threshold']) {
                    $status['level'] = 'red';
                    $status['message'] = 'Faible readiness. Risque accru de fatigue ou blessure.';
                    $status['recommendation'] = 'Repos complet, récupération active très légère, ou réévaluation du plan. Ne pas forcer un entraînement intense.';
                } elseif ($readinessScore < $readinessThresholds['level_orange_threshold']) {
                    $status['level'] = 'orange';
                    $status['message'] = 'Readiness modérée. Signes de fatigue ou de stress.';
                    $status['recommendation'] = "Adapter l'entraînement : réduire le volume/l'intensité ou privilégier la récupération.";
                } elseif ($readinessScore < $readinessThresholds['level_yellow_threshold']) {
                    $status['level'] = 'yellow';
                    $status['message'] = 'Bonne readiness, quelques points à surveiller.';
                    $status['recommendation'] = 'Entraînement normal, mais rester attentif aux sensations et adapter si nécessaire.';
                }
            }

            // Règle d'exception pour la douleur sévère, qui peut surclasser le score
            $morningPain = $allMetrics->where('metric_type', MetricType::MORNING_PAIN->value)
                ->where('date', $today)
                ->first()?->value;
            if ($morningPain !== null && $morningPain >= $readinessThresholds['severe_pain_threshold']) {
                $status['level'] = 'red';
                $status['message'] = 'Douleur sévère signalée. Repos ou consultation médicale.';
                $status['recommendation'] = "Absolument aucun entraînement intense. Focalisation sur la récupération et l'identification de la cause de la douleur.";
            }

            // Règle d'exception pour le premier jour des règles avec niveau d'énergie bas
            $firstDayPeriod = $allMetrics->where('metric_type', MetricType::MORNING_FIRST_DAY_PERIOD->value)
                ->where('date', $today)
                ->first()?->value;
            $preSessionEnergy = $allMetrics->where('metric_type', MetricType::PRE_SESSION_ENERGY_LEVEL->value)
                ->where('date', $today)
                ->first()?->value;

            if ($firstDayPeriod && ($preSessionEnergy !== null && $preSessionEnergy <= $readinessThresholds['menstrual_energy_low'])) {
                // Si l'état n'est pas déjà rouge, on le passe à orange
                if ($status['level'] !== 'red') {
                    $status['level'] = 'orange';
                    $status['message'] = "Premier jour des règles avec niveau d'énergie bas.";
                    $status['recommendation'] = "Adapter l'entraînement aux sensations, privilégier des activités plus douces ou de la récupération active.";
                }
            }

            // Ajout des détails du calcul du score
            $status['details'] = $readinessDetails;
            $status['details_text'] = $this->formatReadinessDetailsToText($readinessDetails);
        }

        return $status;
    }

    protected function checkMissingDailyReadinessMetrics(Collection $allMetrics, Carbon $today): array
    {
        $missingAlerts = [];
        $missingCount = 0;
        $missingMetricNames = [];

        foreach (self::ESSENTIAL_DAILY_READINESS_METRICS as $metricType) {
            $metricExists = $allMetrics->where('metric_type', $metricType->value)
                ->where('date', $today)
                ->isNotEmpty();

            if (! $metricExists) {
                $missingAlerts[] = [
                    'type'    => 'info',
                    'message' => "Donnée manquante : La métrique \"{$metricType->getLabel()}\" n'a pas été enregistrée pour aujourd'hui. Veuillez la remplir pour un calcul complet du score de readiness.",
                ];
                $missingCount++;
                $missingMetricNames[] = $metricType->getLabel();
            }
        }

        // Cas spécifique pour le CIH/CPH qui est hebdomadaire mais dépend des données quotidiennes
        $sessionLoadMetricsThisWeek = $allMetrics->where('metric_type', MetricType::POST_SESSION_SESSION_LOAD->value)
            ->whereBetween('date', [$today->copy()->startOfWeek(Carbon::MONDAY), $today->copy()->endOfWeek(Carbon::SUNDAY)]);
        if ($sessionLoadMetricsThisWeek->isEmpty()) {
            $missingAlerts[] = [
                'type'    => 'info',
                'message' => "Donnée manquante : Aucune \"Charge de Session\" n'a été enregistrée cette semaine. Le calcul du ratio Charge Réelle/Planifiée sera incomplet.",
            ];
        }

        return ['alerts' => $missingAlerts, 'missing_count' => $missingCount, 'missing_metric_names' => $missingMetricNames];
    }

    /**
     * Ajoute un détail de calcul au tableau des détails de readiness.
     *
     * @param  \App\Enums\MetricType|\App\Enums\CalculatedMetricType  $metric  La métrique en Enum.
     * @param  int|float  $penalty  La pénalité appliquée par ce facteur.
     * @param  int  $currentScore  Le score de readiness après application de cette pénalité.
     * @param  int|float|null  $metricValue  Valeur de la métrique.
     * @param  array  $data  Un tableau associatif de données brutes nécessaires pour générer la description.
     */
    protected function addReadinessDetail(MetricType|CalculatedMetricType $metric, int|float $penalty, int $currentScore, int|float|null $metricValue, array $data = []): void
    {
        $this->readinessDetails[] = [
            'metric'             => $metric,
            'metric_short_label' => $metric->getLabelShort(),
            'penalty'            => $penalty,
            'current_score'      => $currentScore,
            'metric_value'       => $metricValue,
            'data'               => $data,
        ];
    }

    /**
     * Formate les détails du calcul du score de readiness en une chaîne de texte lisible.
     *
     * @param  array  $details  Le tableau de détails du score de readiness.
     * @return string Une chaîne de caractères formatée, avec chaque détail sur une nouvelle ligne.
     */
    protected function formatReadinessDetailsToText(array $details): string
    {
        $formattedDetails = [];
        foreach ($details as $detail) {
            $sign = $detail['penalty'] > 0 ? '-' : '+';
            $formattedDetails[] = "{$detail['metric_short_label']} : ".number_format($detail['metric_value'], 1)."/{$detail['metric']->getScale()} ➝ {$sign}".number_format($detail['penalty'], 1).'. ';
        }

        return implode("\n", $formattedDetails);
    }
}
