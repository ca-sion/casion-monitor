<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Athlete;
use App\Enums\MetricType;
use App\Models\TrainingPlanWeek;
use Illuminate\Support\Collection;

class MetricReadinessService
{
    protected MetricCalculationService $metricCalculationService;

    protected MetricTrendsService $metricTrendsService;

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
            'pre_session_penalty_high'       => 15,
            'pre_session_penalty_medium'     => 5,
            'pre_session_leg_feel_low'       => 4,
            'pre_session_leg_feel_medium'    => 6,
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

    public function calculateOverallReadinessScore(Athlete $athlete, Collection $allMetrics): int
    {
        $readinessScore = 100;

        // 1. Impact du SBM (sur 10)
        $sbmMetrics = $allMetrics->whereIn('metric_type', [
            MetricType::MORNING_SLEEP_QUALITY,
            MetricType::MORNING_GENERAL_FATIGUE,
            MetricType::MORNING_PAIN,
            MetricType::MORNING_MOOD_WELLBEING,
        ])->filter(fn ($m) => $m->date->isToday()); // On prend les métriques du jour

        // Calcul du SBM via votre méthode existante (assumée retourner sur 0-10)
        $dailySbm = $this->metricCalculationService->calculateSbmForCollection($sbmMetrics);

        if ($dailySbm !== null) {
            // Impact du SBM : Plus le SBM est bas, plus le score de readiness diminue.
            // Convertissons le SBM sur 10 en un impact sur 100 points de readiness.
            // Si SBM = 10 (parfait), 0 pénalité. Si SBM = 0, pénalité maximale.
            // Ex: (10 - SBM) * 5. Si SBM=10, (10-10)*5=0. Si SBM=0, (10-0)*5=50.
            $readinessScore -= (10 - $dailySbm) * self::ALERT_THRESHOLDS['READINESS_SCORE']['sbm_penalty_factor'];
        }

        // 2. Impact de la VFC (HRV)
        $hrvMetrics = $allMetrics->where('metric_type', MetricType::MORNING_HRV->value)->sortByDesc('date');
        $lastHrv = $hrvMetrics->first()?->value;

        if ($lastHrv !== null) {
            // Calcule la moyenne HRV des 7 derniers jours (hors aujourd'hui) pour la baseline
            $hrv7DayAvg = $hrvMetrics->where('date', '>=', now()->subDays(7)->startOfDay())
                ->where('date', '<', now()->startOfDay())
                ->avg('value');

            if ($hrv7DayAvg && $hrv7DayAvg > 0) {
                $changePercent = (($lastHrv - $hrv7DayAvg) / $hrv7DayAvg) * 100;

                if ($changePercent < self::ALERT_THRESHOLDS['READINESS_SCORE']['hrv_drop_severe_percent']) { // Chute de plus de 10%
                    $readinessScore -= 20; // Cette valeur n'est pas dans ALERT_THRESHOLDS, mais elle est fixe.
                } elseif ($changePercent < self::ALERT_THRESHOLDS['READINESS_SCORE']['hrv_drop_moderate_percent']) { // Chute de 5% à 10%
                    $readinessScore -= 10; // Cette valeur n'est pas dans ALERT_THRESHOLDS, mais elle est fixe.
                }
                // Aucune pénalité si stable ou en hausse.
            }
        }

        // 3. Impact de la Douleur (MORNING_PAIN)
        $morningPain = $allMetrics->where('metric_type', MetricType::MORNING_PAIN->value)
            ->where('date', now()->startOfDay())
            ->first()?->value;

        if ($morningPain !== null && $morningPain > 0) {
            // Pénalité plus forte pour la douleur
            $readinessScore -= ($morningPain * self::ALERT_THRESHOLDS['READINESS_SCORE']['pain_penalty_factor']); // Ex: 4 points par niveau de douleur sur 10
        }

        // 4. Impact des métriques pré-session (remplies juste avant la session)
        $preSessionEnergy = $allMetrics->where('metric_type', MetricType::PRE_SESSION_ENERGY_LEVEL->value)
            ->where('date', now()->startOfDay())
            ->first()?->value;

        if ($preSessionEnergy !== null && $preSessionEnergy <= self::ALERT_THRESHOLDS['READINESS_SCORE']['pre_session_energy_low']) { // Si l'énergie est très basse (1-4)
            $readinessScore -= self::ALERT_THRESHOLDS['READINESS_SCORE']['pre_session_penalty_high'];
        } elseif ($preSessionEnergy !== null && $preSessionEnergy <= self::ALERT_THRESHOLDS['READINESS_SCORE']['pre_session_energy_medium']) { // Si l'énergie est moyenne (5-6)
            $readinessScore -= self::ALERT_THRESHOLDS['READINESS_SCORE']['pre_session_penalty_medium'];
        }

        $preSessionLegFeel = $allMetrics->where('metric_type', MetricType::PRE_SESSION_LEG_FEEL->value)
            ->where('date', now()->startOfDay())
            ->first()?->value;

        if ($preSessionLegFeel !== null && $preSessionLegFeel <= self::ALERT_THRESHOLDS['READINESS_SCORE']['pre_session_leg_feel_low']) { // Si les jambes sont très lourdes (1-4)
            $readinessScore -= self::ALERT_THRESHOLDS['READINESS_SCORE']['pre_session_penalty_high'];
        } elseif ($preSessionLegFeel !== null && $preSessionLegFeel <= self::ALERT_THRESHOLDS['READINESS_SCORE']['pre_session_leg_feel_medium']) { // Si les jambes sont moyennes (5-6)
            $readinessScore -= self::ALERT_THRESHOLDS['READINESS_SCORE']['pre_session_penalty_medium'];
        }

        // 5. Impact du ratio de charge (CIH/CPH) - à évaluer sur la semaine en cours
        $currentWeekStartDate = now()->startOfWeek(Carbon::MONDAY);
        // Assurez-vous que athlete->currentTrainingPlan existe et que trainingPlanWeeks est une collection
        $trainingPlanWeeks = $athlete->currentTrainingPlan?->weeks ?? collect();

        // Trouver la TrainingPlanWeek pour la semaine en cours
        $currentTrainingPlanWeek = $trainingPlanWeeks->firstWhere('start_date', $currentWeekStartDate->toDateString());

        // Filtrer les métriques pour la semaine en cours
        $metricsForCurrentWeek = $allMetrics->whereBetween('date', [$currentWeekStartDate, $currentWeekStartDate->copy()->endOfWeek(Carbon::SUNDAY)]);

        $currentCih = $this->metricCalculationService->calculateCihForCollection($metricsForCurrentWeek);
        $currentCph = $currentTrainingPlanWeek ? $this->metricCalculationService->calculateCph($currentTrainingPlanWeek) : 0;

        if ($currentCih > 0 && $currentCph > 0) {
            $ratio = $currentCih / $currentCph;
            $overloadThreshold = self::ALERT_THRESHOLDS['READINESS_SCORE']['charge_overload_threshold'];

            if ($ratio > $overloadThreshold) { // Surcharge
                $readinessScore -= self::ALERT_THRESHOLDS['READINESS_SCORE']['charge_overload_penalty_factor'] * ($ratio - $overloadThreshold); // Pénalité croissante
            }
            // Pas de pénalité directe pour la sous-charge sur la readiness immédiate ici.
        }

        // Assurez-vous que le score reste entre 0 et 100
        return max(0, min(100, (int) round($readinessScore)));
    }

    public function getAthleteReadinessStatus(Athlete $athlete, Collection $allMetrics): array
    {
        $readinessScore = 100; // Valeur par défaut, sera écrasée ou non calculée
        $readinessThresholds = self::ALERT_THRESHOLDS['READINESS_SCORE'];

        $status = [
            'level'           => 'green',
            'message'         => "L'athlète est prêt pour l'entraînement !",
            'readiness_score' => null,
            'recommendation'  => "Poursuivre l'entraînement planifié.",
            'alerts'          => [],
        ];

        // Vérification des métriques quotidiennes manquantes
        $missingDailyMetricsData = $this->checkMissingDailyReadinessMetrics($allMetrics);
        $status['alerts'] = $missingDailyMetricsData['alerts'];
        $missingCount = $missingDailyMetricsData['missing_count'];
        $missingMetricNames = $missingDailyMetricsData['missing_metric_names'];

        if ($missingCount > 3) {
            $missingNamesString = implode(', ', $missingMetricNames);
            $status['level'] = 'neutral';
            $status['message'] = 'Score de readiness non calculable.';
            $status['recommendation'] = "Trop de données essentielles sont manquantes pour aujourd'hui ({$missingCount} manquantes : {$missingNamesString}). Veuillez remplir toutes les métriques quotidiennes pour obtenir un score précis.";
            $status['readiness_score'] = 'N/A'; // Indiquer que le score n'est pas disponible
        } else {
            // Calcul du score global de readiness UNIQUEMENT si pas trop de données manquantes
            $readinessScore = $this->calculateOverallReadinessScore($athlete, $allMetrics);
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
            // Si >= 85, reste 'green' par default

            // Règle d'exception pour la douleur sévère, qui peut surclasser le score
            $morningPain = $allMetrics->where('metric_type', MetricType::MORNING_PAIN->value)
                ->where('date', now()->startOfDay())
                ->first()?->value;
            if ($morningPain !== null && $morningPain >= $readinessThresholds['severe_pain_threshold']) { // Douleur de 7/10 ou plus
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
}
