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
    ];

    /**
     * Calcule le score global de readiness de l'athlète en prenant en compte divers facteurs.
     * Retourne le score final et un tableau détaillé des pénalités appliquées.
     *
     * @param  Athlete  $athlete  L'athlète pour lequel calculer le score.
     * @param  Collection  $allMetrics  Toutes les métriques disponibles pour l'athlète.
     * @return array Un tableau contenant 'readiness_score' (int) et 'readiness_details' (array).
     */
    public function calculateOverallReadinessScore(Athlete $athlete, Collection $allMetrics): array
    {
        $readinessScore = 100;
        $this->readinessDetails = [];
        $today = now()->startOfDay();

        // 1. Impact du SBM (Subjective Well-being Metrics) - Read from calculated metrics
        $dailySbm = CalculatedMetric::where('athlete_id', $athlete->id)
            ->where('date', $today)
            ->where('type', CalculatedMetricType::SBM)
            ->value('value');

        $sbmPenalty = 0;
        if ($dailySbm !== null) {
            $sbmPenalty = (10 - $dailySbm) * self::ALERT_THRESHOLDS['READINESS_SCORE']['sbm_penalty_factor'];
        }
        $readinessScore -= $sbmPenalty;
        $readinessScore = max(0, min(100, (int) round($readinessScore)));
        $this->addReadinessDetail(CalculatedMetricType::SBM, $sbmPenalty, $readinessScore, $dailySbm);

        // 2. Impact de la VFC (Variabilité de la Fréquence Cardiaque - HRV)
        $hrvMetrics = $allMetrics->where('metric_type', MetricType::MORNING_HRV->value)->sortByDesc('date');
        $lastHrv = $hrvMetrics->first()?->value;
        $hrvPenalty = 0;
        $changePercent = 0;

        if ($lastHrv !== null) {
            $hrv7DayAvg = $hrvMetrics->where('date', '>=', now()->subDays(7)->startOfDay())
                ->where('date', '<', now()->startOfDay())
                ->avg('value');

            if ($hrv7DayAvg && $hrv7DayAvg > 0) {
                $changePercent = (($lastHrv - $hrv7DayAvg) / $hrv7DayAvg) * 100;

                if ($changePercent < self::ALERT_THRESHOLDS['READINESS_SCORE']['hrv_drop_severe_percent']) {
                    $hrvPenalty = 20;
                } elseif ($changePercent < self::ALERT_THRESHOLDS['READINESS_SCORE']['hrv_drop_moderate_percent']) {
                    $hrvPenalty = 10;
                }
            }
        }
        $readinessScore -= $hrvPenalty;
        $readinessScore = max(0, min(100, (int) round($readinessScore)));
        $this->addReadinessDetail(MetricType::MORNING_HRV, $hrvPenalty, $readinessScore, $lastHrv, ['change_percent' => $changePercent]);

        // 3. Impact de la Douleur (MORNING_PAIN)
        $morningPain = $allMetrics->where('metric_type', MetricType::MORNING_PAIN->value)
            ->where('date', $today)
            ->first()?->value;
        $painPenalty = 0;
        if ($morningPain !== null && $morningPain > 0) {
            $painPenalty = ($morningPain * self::ALERT_THRESHOLDS['READINESS_SCORE']['pain_penalty_factor']);
        }
        $readinessScore -= $painPenalty;
        $readinessScore = max(0, min(100, (int) round($readinessScore)));
        $this->addReadinessDetail(MetricType::MORNING_PAIN, $painPenalty, $readinessScore, $morningPain);

        // 4. Impact des métriques pré-session (remplies juste avant la session)
        $preSessionEnergy = $allMetrics->where('metric_type', MetricType::PRE_SESSION_ENERGY_LEVEL->value)
            ->where('date', $today)
            ->first()?->value;
        $preSessionEnergyPenalty = 0;
        if ($preSessionEnergy !== null) {
            if ($preSessionEnergy <= self::ALERT_THRESHOLDS['READINESS_SCORE']['pre_session_energy_low']) {
                $preSessionEnergyPenalty = self::ALERT_THRESHOLDS['READINESS_SCORE']['pre_session_penalty_high'];
            } elseif ($preSessionEnergy <= self::ALERT_THRESHOLDS['READINESS_SCORE']['pre_session_energy_medium']) {
                $preSessionEnergyPenalty = self::ALERT_THRESHOLDS['READINESS_SCORE']['pre_session_penalty_medium'];
            }
        }
        $readinessScore -= $preSessionEnergyPenalty;
        $readinessScore = max(0, min(100, (int) round($readinessScore)));
        $this->addReadinessDetail(MetricType::PRE_SESSION_ENERGY_LEVEL, $preSessionEnergyPenalty, $readinessScore, $preSessionEnergy);

        $preSessionLegFeel = $allMetrics->where('metric_type', MetricType::PRE_SESSION_LEG_FEEL->value)
            ->where('date', $today)
            ->first()?->value;
        $preSessionLegFeelPenalty = 0;
        if ($preSessionLegFeel !== null) {
            if ($preSessionLegFeel <= self::ALERT_THRESHOLDS['READINESS_SCORE']['pre_session_leg_feel_low']) {
                $preSessionLegFeelPenalty = self::ALERT_THRESHOLDS['READINESS_SCORE']['pre_session_penalty_high'];
            } elseif ($preSessionLegFeel <= self::ALERT_THRESHOLDS['READINESS_SCORE']['pre_session_leg_feel_medium']) {
                $preSessionLegFeelPenalty = self::ALERT_THRESHOLDS['READINESS_SCORE']['pre_session_penalty_medium'];
            }
        }
        $readinessScore -= $preSessionLegFeelPenalty;
        $readinessScore = max(0, min(100, (int) round($readinessScore)));
        $this->addReadinessDetail(MetricType::PRE_SESSION_LEG_FEEL, $preSessionLegFeelPenalty, $readinessScore, $preSessionLegFeel);

        // 5. Impact du ratio de charge (CIH/CPH) - Read from calculated metrics
        $chargeRatio = CalculatedMetric::where('athlete_id', $athlete->id)
            ->where('date', $today)
            ->where('type', CalculatedMetricType::RATIO_CIH_NORMALIZED_CPH)
            ->value('value');

        $chargeRatioPenalty = 0;
        $overloadThreshold = self::ALERT_THRESHOLDS['READINESS_SCORE']['charge_overload_threshold'];

        if ($chargeRatio !== null && $chargeRatio > $overloadThreshold) {
            $chargeRatioPenalty = self::ALERT_THRESHOLDS['READINESS_SCORE']['charge_overload_penalty_factor'] * ($chargeRatio - $overloadThreshold);
        }

        $readinessScore -= $chargeRatioPenalty;
        $readinessScore = max(0, min(100, (int) round($readinessScore)));
        $this->addReadinessDetail(CalculatedMetricType::RATIO_CIH_CPH, $chargeRatioPenalty, $readinessScore, $chargeRatio, ['ratio' => $chargeRatio, 'overload_threshold' => $overloadThreshold]);

        return [
            'readiness_score'   => $readinessScore,
            'readiness_details' => $this->readinessDetails,
        ];
    }

    /**
     * Récupère le statut de readiness de l'athlète, incluant le score, le niveau, le message,
     * la recommandation, les alertes et le détail du calcul du score.
     *
     * @param  Athlete  $athlete  L'athlète pour lequel récupérer le statut.
     * @param  Collection  $allMetrics  Toutes les métriques disponibles pour l'athlète.
     * @return array Un tableau associatif contenant le statut complet de readiness.
     */
    public function getAthleteReadinessStatus(Athlete $athlete, Collection $allMetrics): array
    {
        $readinessScore = 100;
        $readinessThresholds = self::ALERT_THRESHOLDS['READINESS_SCORE'];

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
        $missingDailyMetricsData = $this->checkMissingDailyReadinessMetrics($allMetrics);
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
            $readinessResult = $this->calculateOverallReadinessScore($athlete, $allMetrics);
            $readinessScore = $readinessResult['readiness_score'];
            $readinessDetails = $readinessResult['readiness_details'];

            $status['readiness_score'] = $readinessScore;

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

            // Règle d'exception pour la douleur sévère, qui peut surclasser le score
            $morningPain = $allMetrics->where('metric_type', MetricType::MORNING_PAIN->value)
                ->where('date', now()->startOfDay())
                ->first()?->value;
            if ($morningPain !== null && $morningPain >= $readinessThresholds['severe_pain_threshold']) {
                $status['level'] = 'red';
                $status['message'] = 'Douleur sévère signalée. Repos ou consultation médicale.';
                $status['recommendation'] = "Absolument aucun entraînement intense. Focalisation sur la récupération et l'identification de la cause de la douleur.";
            }

            // Règle d'exception pour le premier jour des règles avec niveau d'énergie bas
            $firstDayPeriod = $allMetrics->where('metric_type', MetricType::MORNING_FIRST_DAY_PERIOD->value)
                ->where('date', now()->startOfDay())
                ->first()?->value;
            $preSessionEnergy = $allMetrics->where('metric_type', MetricType::PRE_SESSION_ENERGY_LEVEL->value)
                ->where('date', now()->startOfDay())
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

    protected function checkMissingDailyReadinessMetrics(Collection $allMetrics): array
    {
        $missingAlerts = [];
        $missingCount = 0;
        $missingMetricNames = [];
        $today = now()->startOfDay();

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
            ->whereBetween('date', [now()->startOfWeek(Carbon::MONDAY), now()->endOfWeek(Carbon::SUNDAY)]);
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
