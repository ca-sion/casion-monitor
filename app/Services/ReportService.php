<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Athlete;
use App\Enums\MetricType;
use App\Enums\CalculatedMetric;
use Illuminate\Support\Collection;
use App\Models\Metric; // Pour le typage et l'accès aux données de base

/**
 * ReportService : Orchestre la génération de rapports analytiques et narratifs.
 * Il centralise la logique de dépistage avancé (Damping, ACWR, Incohérence) pour
 * fournir une analyse personnalisée et proactive.
 */
class ReportService
{
    protected MetricCalculationService $calculationService;
    protected MetricTrendsService $trendsService;
    protected MetricAlertsService $alertsService;
    protected MetricReadinessService $readinessService;
    protected MetricMenstrualService $menstrualService;
    protected GamificationService $gamificationService;

    public function __construct(
        MetricCalculationService $calculationService,
        MetricTrendsService $trendsService,
        MetricAlertsService $alertsService,
        MetricReadinessService $readinessService,
        MetricMenstrualService $menstrualService,
        GamificationService $gamificationService
    ) {
        $this->calculationService = $calculationService;
        $this->trendsService = $trendsService;
        $this->alertsService = $alertsService;
        $this->readinessService = $readinessService;
        $this->menstrualService = $menstrualService;
        $this->gamificationService = $gamificationService;
    }

    /**
     * Point d'entrée unique. Génère un rapport d'analyse complet.
     */
    public function generateReport(Athlete $athlete, string $periodType, Carbon $endDate): array
    {
        $period = $this->getPeriodDates($periodType, $endDate);
        
        // On récupère TOUTES les métriques nécessaires pour la plus grande période de calcul (ACWR/Damping)
        // On trie par date descendante pour fiabiliser les `take()`
        $requiredMetrics = $athlete->metrics()
            ->whereBetween('date', [$endDate->copy()->subMonths(6)->startOfDay(), $endDate->endOfDay()])
            ->orderBy('date', 'desc')
            ->get();
        
        $report = [
            'athlete_id' => $athlete->id,
            'period_type' => $periodType,
            'end_date' => $period['endDate']->toDateString(),
            'sections' => [],
        ];

        switch ($periodType) {
            case 'daily':
                $report['sections'] = $this->generateDailyAnalysis($athlete, $requiredMetrics, $endDate);
                break;
            case 'weekly':
                $report['sections'] = $this->generateWeeklyAnalysis($athlete, $requiredMetrics, $endDate);
                break;
            case 'monthly':
                $report['sections'] = $this->generateMonthlyAnalysis($athlete, $requiredMetrics, $endDate);
                break;
            case 'biannual':
                $report['sections'] = $this->generateBiannualAnalysis($athlete, $requiredMetrics, $endDate);
                break;
            default:
                $report['sections']['error'] = ['title' => 'Erreur', 'narrative' => 'Type de rapport non supporté.'];
        }

        $report['sections']['gamification'] = $this->getGamificationSummary($athlete);

        return $report;
    }

    // UTILS
    protected function getPeriodDates(string $periodType, Carbon $endDate): array
    {
        $startDate = $endDate->copy();
        $days = match ($periodType) {
            'daily' => 0, 'weekly' => 6, 'monthly' => 29, 'biannual' => 182, default => 0,
        };
        return ['startDate' => $startDate->subDays($days), 'endDate' => $endDate];
    }

    protected function getGamificationSummary(Athlete $athlete): array
    {
        $gamification = $this->gamificationService->getGamificationData($athlete);
        
        $data = [
            'title' => 'Motivation : votre engagement',
            'status' => 'optimal',
            'main_metric' => [
                'value' => $gamification['current_streak'],
                'label' => 'Jours de série',
            ],
            'summary' => "Félicitations, vous êtes au niveau {$gamification['level']} avec {$gamification['points']} points.",
            'points' => [
                ['status' => 'optimal', 'text' => "Votre série de saisie actuelle est de {$gamification['current_streak']} jours (Record : {$gamification['longest_streak']} jours). Continuez comme ça !"]
            ],
            'recommendation' => null,
        ];
        
        if (!empty($gamification['new_badges'])) {
            foreach ($gamification['new_badges'] as $badge) {
                $data['points'][] = ['status' => 'optimal', 'text' => "Nouveau Badge débloqué ! : {$badge}"];
            }
        }

        return $data;
    }

    // DAILY ANALYSIS
    protected function generateDailyAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $dailyMetrics = $allMetrics->where('date', $endDate->toDateString());
        $previousDayMetrics = $allMetrics->where('date', $endDate->copy()->subDay()->toDateString());

        return [
            'readiness_status' => $this->getReadinessStatus($athlete, $dailyMetrics, $allMetrics),
            'alerts_and_inconsistencies' => $this->getInconsistencyAlerts($athlete, $dailyMetrics),
            'j_minus_1_correlation' => $this->getInterDayCorrelation($athlete, $allMetrics, $endDate),
            'recommendation' => $this->getDailyRecommendation($athlete, $dailyMetrics),
        ];
    }

    protected function getReadinessStatus(Athlete $athlete, Collection $dailyMetrics, Collection $allMetrics): array
    {
        $readinessStatusData = $this->readinessService->getAthleteReadinessStatus($athlete, $allMetrics);
        $sbmHistory = $this->getCalculatedMetricHistory($athlete, CalculatedMetric::SBM, 7, Carbon::now(), $allMetrics);
        $sbmTrend = $this->trendsService->calculateGenericNumericTrend($sbmHistory);

        $level = $readinessStatusData['level'] ?? 'neutral';
        $score = $readinessStatusData['readiness_score'] ?? 0;
        $mainPenaltyReason = $readinessStatusData['details'][0]['metric_short_label'] ?? $readinessStatusData['message'] ?? 'Facteur inconnu';

        $statusMap = ['red' => 'high_risk', 'orange' => 'warning', 'yellow' => 'warning', 'green' => 'optimal'];
        
        $data = [
            'title' => 'Statut de Readiness quotidien',
            'status' => $statusMap[$level] ?? 'neutral',
            'main_metric' => [
                'value' => $score,
                'label' => 'Score /100',
            ],
            'summary' => "Votre score de readiness est de {$score}/100.",
            'points' => [],
            'recommendation' => null,
            'details' => $readinessStatusData['details'] ?? []
        ];

        if ($level === 'red') {
            $data['summary'] = 'Alerte Rouge';
            $data['points'][] = ['status' => 'high_risk', 'text' => "Un facteur majeur ({$mainPenaltyReason}) impacte sévèrement votre capacité à performer."];
            $data['recommendation'] = "Consultez votre entraîneur. Une modification ou un report de la session est probable.";
        } elseif ($level === 'orange' || $level === 'yellow') {
            $trendChange = number_format(abs($sbmTrend['change'] ?? 0), 1);
            $data['summary'] = 'Alerte Modérée';
            $data['points'][] = ['status' => 'warning', 'text' => "Votre SBM est en baisse de {$trendChange}% sur 7 jours."];
            $data['points'][] = ['status' => 'warning', 'text' => "Facteur principal : {$mainPenaltyReason}."];
            $data['recommendation'] = "Allégez légèrement votre charge d'entraînement aujourd'hui.";
        } else {
            $data['summary'] = 'Récupération Optimale';
            $data['points'][] = ['status' => 'optimal', 'text' => 'Votre SBM est très stable. Vous êtes prêt à performer.'];
            $data['recommendation'] = "Continuez sur cette lancée !";
        }

        return $data;
    }

    protected function getInconsistencyAlerts(Athlete $athlete, Collection $dailyMetrics): array
    {
        $alerts = $this->alertsService->checkAllAlerts($athlete, $dailyMetrics);
        $inconsistencies = [];

        // ... (existing inconsistency logic)
        $sessionLoad = $dailyMetrics->firstWhere('metric_type', MetricType::POST_SESSION_SESSION_LOAD->value)?->value;
        $subjectiveFatigue = $dailyMetrics->firstWhere('metric_type', MetricType::POST_SESSION_SUBJECTIVE_FATIGUE->value)?->value;
        if ($sessionLoad !== null && $subjectiveFatigue !== null && $sessionLoad < 4 && $subjectiveFatigue > 7) {
            $inconsistencies[] = ['status' => 'warning', 'text' => "Charge Faible ({$sessionLoad}/10) mais Fatigue Élevée ({$subjectiveFatigue}/10). Hypothèse : Fatigue mentale, nutrition ou hydratation."];
        }
        
        $hrv = $dailyMetrics->firstWhere('metric_type', MetricType::MORNING_HRV->value)?->value;
        $mood = $dailyMetrics->firstWhere('metric_type', MetricType::MORNING_MOOD_WELLBEING->value)?->value;
        if ($hrv !== null && $mood !== null) {
            $hrvAvg = $athlete->metrics()->where('metric_type', MetricType::MORNING_HRV->value)->avg('value');
            if ($hrvAvg > 0 && $hrv < $hrvAvg * 0.90 && $mood > 8) { 
                $inconsistencies[] = ['status' => 'warning', 'text' => "VFC basse ({$hrv}ms) mais Humeur excellente ({$mood}/10). Hypothèse : Forte motivation masquant une fatigue physiologique."];
            }
        }
        
        $energyLevel = $dailyMetrics->firstWhere('metric_type', MetricType::PRE_SESSION_ENERGY_LEVEL->value)?->value;
        $perfFeel = $dailyMetrics->firstWhere('metric_type', MetricType::POST_SESSION_PERFORMANCE_FEEL->value)?->value;
        if ($energyLevel !== null && $perfFeel !== null && $energyLevel > 8 && $perfFeel < 5) {
             $inconsistencies[] = ['status' => 'warning', 'text' => "Énergie Pré-session élevée ({$energyLevel}/10) mais Performance Post-session faible ({$perfFeel}/10). Hypothèse : Problème tactique ou technique."];
        }

        $allPoints = array_merge(
            array_map(fn($a) => ['status' => 'high_risk', 'text' => $a['message']], $alerts),
            $inconsistencies
        );

        return [
            'title' => 'Alertes et Incohérences',
            'status' => empty($allPoints) ? 'optimal' : 'warning',
            'main_metric' => null,
            'summary' => empty($allPoints) ? 'Aucune alerte ou incohérence détectée.' : (count($allPoints) . ' point(s) à surveiller.'),
            'points' => $allPoints,
            'recommendation' => empty($allPoints) ? null : 'Analysez ces points pour mieux comprendre votre état de fatigue réel.'
        ];
    }

    protected function getInterDayCorrelation(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $currentSbm = $this->calculationService->calculateSbmForCollection($allMetrics->where('date', $endDate->toDateString()));
        
        $sbmHistory = $this->getCalculatedMetricHistory($athlete, CalculatedMetric::SBM, 14, $endDate, $allMetrics);
        $loadHistory = $allMetrics
            ->where('metric_type', MetricType::POST_SESSION_SESSION_LOAD->value)
            ->where('date', '>=', $endDate->copy()->subDays(14))
            ->map(fn($m) => (object)['date' => $m->date->toDateString(), 'value' => $m->value]);

        $sbmHistoryShifted = $sbmHistory->map(fn($s) => (object)['date' => Carbon::parse($s->date)->subDay()->toDateString(), 'value' => $s->value]);
        $correlationData = $this->trendsService->calculateCorrelationFromCollections($loadHistory, $sbmHistoryShifted);

        $data = [
            'title' => 'Corrélation J-1 vs J : l\'impact de l\'effort',
            'status' => 'neutral',
            'main_metric' => null,
            'summary' => "Votre SBM d'aujourd'hui est de ".number_format($currentSbm, 1).".",
            'points' => [],
            'recommendation' => null,
        ];

        if (isset($correlationData['correlation']) && $correlationData['correlation'] !== null) {
            $correlation = $correlationData['correlation'];
            $data['main_metric'] = [
                'value' => number_format($correlation, 2),
                'label' => 'Corrélation Charge/SBM'
            ];
            if ($correlation < -0.6) {
                $data['points'][] = ['status' => 'warning', 'text' => "Forte corrélation négative : Les charges élevées ont un impact direct et significatif sur votre récupération du lendemain."];
                $data['recommendation'] = "Prévoyez des stratégies de compensation (nutrition, sommeil) après les grosses séances.";
            } else {
                $data['points'][] = ['status' => 'optimal', 'text' => "L'impact direct de la charge d'hier est variable. Votre récupération dépend peut-être davantage d'autres facteurs comme le sommeil ou le stress."];
            }
        } else {
            $data['points'][] = ['status' => 'neutral', 'text' => "Plus de données sont requises pour une analyse de corrélation fiable sur 14 jours."];
        }
        
        return $data;
    }

    protected function getDailyRecommendation(Athlete $athlete, Collection $dailyMetrics): array
    {
        $readinessStatusData = $this->readinessService->getAthleteReadinessStatus($athlete, $dailyMetrics);
        $status = $readinessStatusData['level'] ?? 'neutral';
        $inconsistencies = $this->getInconsistencyAlerts($athlete, $dailyMetrics)['points'];
        $sleepDuration = $dailyMetrics->firstWhere('metric_type', MetricType::MORNING_SLEEP_DURATION->value)?->value;
        $legFeel = $dailyMetrics->firstWhere('metric_type', MetricType::PRE_SESSION_LEG_FEEL->value)?->value;

        $statusMap = ['red' => 'high_risk', 'orange' => 'warning', 'yellow' => 'warning', 'green' => 'optimal'];

        $recommendationText = match ($status) {
            'red' => 'Session Annulée ou Fortement Modifiée. Priorité absolue à la récupération passive ou aux soins.',
            'orange', 'yellow' => (count($inconsistencies) > 0) 
                        ? 'Réduction de 15% de la charge prévue. Évitez les efforts maximaux et concentrez-vous sur la technique.' 
                        : ( (int)$legFeel < 5 ? 'Maintenez le plan mais réduisez l\'intensité si le ressenti des jambes est faible.' : 'Maintenez le plan, mais soyez particulièrement vigilant aux signaux de votre corps.'),
            'green' => ($sleepDuration !== null && $sleepDuration < 7) 
                       ? 'Feu vert ! Visez une performance de qualité, mais planifiez une heure de sommeil en plus cette nuit.'
                       : 'Feu vert ! C\'est une journée idéale pour une séance de haute performance.',
            default => 'Assurez-vous d\'avoir entré toutes vos métriques matinales pour obtenir une recommandation.',
        };
        
        $summaryText = match ($status) {
            'red' => 'Alerte Rouge',
            'orange', 'yellow' => 'Alerte Modérée',
            'green' => 'Readiness Optimale',
            default => 'Données manquantes'
        };

        return [
            'title' => 'Recommandation du Jour',
            'status' => $statusMap[$status] ?? 'neutral',
            'main_metric' => null,
            'summary' => $summaryText,
            'points' => [],
            'recommendation' => $recommendationText,
        ];
    }

    // WEEKLY ANALYSIS
    protected function generateWeeklyAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        return [
            'load_adherence' => $this->getLoadAdherenceAnalysis($athlete, $endDate),
            'acwr_risk_assessment' => $this->getAcwrAnalysis($athlete, $endDate),
            'recovery_debt' => $this->getRecoveryDebtAnalysis($athlete, $allMetrics, $endDate),
            'day_patterns' => $this->getDayPatternsAnalysis($athlete, $allMetrics, $endDate),
        ];
    }

    protected function getLoadAdherenceAnalysis(Athlete $athlete, Carbon $endDate): array
    {
        $ratioCihCph = $this->calculationService->getLastRatioCihCph($athlete, $endDate);

        $data = [
            'title' => 'Adhésion charge planifiée (CPH)',
            'main_metric' => [
                'value' => $ratioCihCph > 0 ? number_format($ratioCihCph, 2) : 'N/A',
                'label' => 'Ratio CIH/CPH',
            ],
            'points' => [],
            'recommendation' => null,
        ];

        if ($ratioCihCph > 1.3) {
            $data['status'] = 'warning';
            $data['summary'] = 'Surcharge';
            $data['points'][] = ['status' => 'warning', 'text' => 'Vous êtes en zone de surentraînement fonctionnel. Votre ressenti de charge dépasse de plus de 30% le plan.'];
            $data['recommendation'] = 'Réduction de 20% de la charge du plan pour la semaine prochaine.';
        } elseif ($ratioCihCph > 0 && $ratioCihCph < 0.7) {
            $data['status'] = 'warning';
            $data['summary'] = 'Sous-charge';
            $data['points'][] = ['status' => 'warning', 'text' => 'La charge perçue est significativement plus faible que planifiée.'];
            $data['recommendation'] = 'Augmentez le RPE (Ressenti de l\'Effort) des prochaines sessions pour mieux correspondre à l\'intensité prévue.';
        } elseif ($ratioCihCph > 0) {
            $data['status'] = 'optimal';
            $data['summary'] = 'Adhésion Optimale';
            $data['points'][] = ['status' => 'optimal', 'text' => 'Excellent équilibre ! Votre ressenti de charge correspond parfaitement à ce qui était planifié (dans la fourchette de 0.8 à 1.3).'];
        } else {
            $data['status'] = 'neutral';
            $data['summary'] = 'Données insuffisantes';
            $data['points'][] = ['status' => 'neutral', 'text' => 'Données insuffisantes ou plan d\'entraînement non trouvé pour calculer l\'adhésion.'];
        }

        return $data;
    }

    protected function getAcwrAnalysis(Athlete $athlete, Carbon $endDate): array
    {
        $acwrData = $this->trendsService->calculateAcwr($athlete, $endDate);
        $acwr = $acwrData['ratio'] ?? 0;

        $data = [
            'title' => 'Dépistage du risque de surcharge (ACWR)',
            'main_metric' => [
                'value' => $acwr > 0 ? number_format($acwr, 2) : 'N/A',
                'label' => 'ACWR',
            ],
            'points' => [],
            'recommendation' => null,
        ];

        if ($acwr >= 1.5) {
            $data['status'] = 'high_risk';
            $data['summary'] = 'Risque Élevé de Blessure';
            $data['points'][] = ['status' => 'high_risk', 'text' => 'Votre charge aiguë est plus de 50% supérieure à votre charge chronique.'];
            $data['recommendation'] = 'Réduction URGENTE de la charge de travail de 20% immédiatement.';
        } elseif ($acwr >= 1.3 && $acwr < 1.5) {
            $data['status'] = 'warning';
            $data['summary'] = 'Zone d\'Alarme';
            $data['points'][] = ['status' => 'warning', 'text' => 'Vous êtes dans la "Zone Rouge" de tolérance à la charge, idéale pour la surcompensation mais risquée.'];
            $data['recommendation'] = 'Doublez les efforts de récupération (sommeil, nutrition) et soyez vigilant aux signaux de votre corps.';
        } elseif ($acwr > 0 && $acwr < 0.8) {
            $data['status'] = 'low_risk';
            $data['summary'] = 'Charge Insuffisante';
            $data['points'][] = ['status' => 'low_risk', 'text' => 'Votre charge de travail actuelle est trop faible, ce qui peut entraîner une désadaptation.'];
            $data['recommendation'] = 'Augmentez l\'intensité ou le volume pour stimuler une nouvelle adaptation.';
        } elseif ($acwr > 0) {
            $data['status'] = 'optimal';
            $data['summary'] = 'Zone Optimale';
            $data['points'][] = ['status' => 'optimal', 'text' => 'Votre progression de charge est sûre et efficace (ratio entre 0.8 et 1.3). Excellent travail.'];
        } else {
            $data['status'] = 'neutral';
            $data['summary'] = 'Données insuffisantes';
            $data['points'][] = ['status' => 'neutral', 'text' => 'Pas assez de données de charge sur les 4 dernières semaines pour calculer l\'ACWR.'];
        }

        return $data;
    }

    protected function getRecoveryDebtAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $sbmHistory30d = $this->getCalculatedMetricHistory($athlete, CalculatedMetric::SBM, 30, $endDate, $allMetrics);
        
        $data = [
            'title' => 'Dette de récupération (fatigue aiguë vs chronique)',
            'main_metric' => null,
            'points' => [],
            'recommendation' => null,
        ];

        if ($sbmHistory30d->count() < 10) {
            $data['status'] = 'neutral';
            $data['summary'] = 'Données insuffisantes';
            $data['points'][] = ['status' => 'neutral', 'text' => 'Moins de 10 jours de données SBM disponibles sur le dernier mois.'];
            return $data;
        }
        
        $sbmAvg7d = $sbmHistory30d->sortByDesc('date')->take(7)->avg('value');
        $sbmAvg30d = $sbmHistory30d->avg('value');
        
        $diffPercent = ($sbmAvg30d > 0) ? (($sbmAvg7d - $sbmAvg30d) / $sbmAvg30d) * 100 : 0;

        $data['main_metric'] = [
            'value' => number_format($diffPercent, 1) . '%',
            'label' => 'Tendance SBM 7j vs 30j',
        ];
        $data['points'][] = ['status' => 'neutral', 'text' => "SBM moyen sur 7 jours : ".number_format($sbmAvg7d, 1)."."];
        $data['points'][] = ['status' => 'neutral', 'text' => "SBM moyen sur 30 jours : ".number_format($sbmAvg30d, 1)."."];

        if ($diffPercent < -5) {
            $data['status'] = 'warning';
            $data['summary'] = 'Dette de Récupération';
            $data['points'][] = ['status' => 'warning', 'text' => "Votre SBM moyen est en baisse de ".number_format(abs($diffPercent), 1)."% cette semaine par rapport à votre moyenne mensuelle."];
            $data['recommendation'] = 'Prévoir un jour de repos additionnel ou une séance de récupération active.';
        } else {
            $data['status'] = 'optimal';
            $data['summary'] = 'Équilibre Maintenu';
            $data['points'][] = ['status' => 'optimal', 'text' => 'Votre niveau de récupération à court terme est stable par rapport à votre tendance de fond.'];
        }

        return $data;
    }

    protected function getDayPatternsAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $recentMetrics = $allMetrics->where('date', '>=', $endDate->copy()->subWeeks(4));
        
        $data = [
            'title' => 'Patterns et jours clés (4 Semaines)',
            'status' => 'neutral',
            'main_metric' => null,
            'summary' => 'Analyse des tendances hebdomadaires.',
            'points' => [],
            'recommendation' => null,
        ];

        if ($recentMetrics->count() < 10) {
            $data['summary'] = 'Données insuffisantes';
            $data['points'][] = ['status' => 'neutral', 'text' => 'Pas assez de données sur les 4 dernières semaines pour identifier des tendances fiables.'];
            return $data;
        }

        $dayAvgPerformance = $recentMetrics->where('metric_type', MetricType::POST_SESSION_PERFORMANCE_FEEL->value)->groupBy(fn ($m) => $m->date->englishDayOfWeek)->map(fn ($g) => $g->avg('value'));
        $dayAvgLegFeel = $recentMetrics->where('metric_type', MetricType::PRE_SESSION_LEG_FEEL->value)->groupBy(fn ($m) => $m->date->englishDayOfWeek)->map(fn ($g) => $g->avg('value'));

        $bestPerfDay = $dayAvgPerformance->sortDesc()->keys()->first();
        $worstLegFeelDay = $dayAvgLegFeel->sort()->keys()->first();
        
        if ($bestPerfDay) {
            $data['points'][] = ['status' => 'optimal', 'text' => "Jour de Pic : Vous semblez performer le mieux le {$bestPerfDay}."];
        }
        if ($worstLegFeelDay) {
            $data['points'][] = ['status' => 'warning', 'text' => "Jour de Sensibilité : Vos jambes sont en moyenne les plus lourdes le {$worstLegFeelDay}."];
        }
        if (empty($data['points'])) {
            $data['points'][] = ['status' => 'neutral', 'text' => 'Votre performance et votre ressenti sont stables tout au long de la semaine.'];
        }

        return $data;
    }

    // MONTHLY ANALYSIS
    protected function generateMonthlyAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $startDate = $endDate->copy()->subDays(29);
        $sections = [
            'damping_summary' => $this->getDampingSummary($athlete, $startDate, $endDate),
            'sleep_impact' => $this->getSleepImpactAnalysis($athlete, $allMetrics, $endDate),
            'pain_hotspot' => $this->getPainHotspotAnalysis($athlete, $allMetrics, $endDate),
        ];

        if (method_exists($athlete, 'isFemale') && $athlete->isFemale()) { 
            $sections['menstrual_summary'] = $this->getMenstrualImpactSummary($athlete);
        }
        return $sections;
    }

    protected function getDampingSummary(Athlete $athlete, Carbon $startDate, Carbon $endDate): array
    {
        $dampingCount = $this->trendsService->getDampingCount($athlete, $startDate, $endDate);
        
        $data = [
            'title' => 'Dépistage de l\'amortissement psychologique (Damping)',
            'main_metric' => [
                'value' => $dampingCount,
                'label' => 'Jours de Damping',
            ],
            'points' => [],
            'recommendation' => null,
        ];

        if ($dampingCount === 0) {
            $data['status'] = 'optimal';
            $data['summary'] = 'Stabilité Menta-Physio';
            $data['points'][] = ['status' => 'optimal', 'text' => "Aucun jour d'Amortissement Psychologique détecté sur les 30 derniers jours."];
        } else {
            $data['status'] = 'warning';
            $data['summary'] = "Damping fréquent détecté {$dampingCount} fois";
            $data['points'][] = ['status' => 'warning', 'text' => "Votre moral masque potentiellement votre fatigue physiologique. C'est un signal précoce de surentraînement."];
            $data['recommendation'] = "Prenez un jour de repos *mental* complet pour déconnecter.";
        }

        return $data;
    }
    
    protected function getSleepImpactAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $correlationData = $this->trendsService->calculateCorrelation($athlete, MetricType::MORNING_SLEEP_DURATION, MetricType::MORNING_GENERAL_FATIGUE, 30);
        $avgDuration = $allMetrics->where('metric_type', MetricType::MORNING_SLEEP_DURATION->value)->where('date', '>=', $endDate->copy()->subDays(29))->avg('value');

        $data = [
            'title' => 'Analyse de l\'impact du sommeil (30j)',
            'main_metric' => [
                'value' => number_format($avgDuration, 1),
                'label' => 'Heures / nuit',
            ],
            'summary' => 'Votre durée moyenne de sommeil est de '.number_format($avgDuration, 1).' heures.',
            'points' => [],
            'recommendation' => null,
        ];

        if (isset($correlationData['correlation'])) {
            $correlation = $correlationData['correlation'];
            $data['points'][] = ['status' => 'neutral', 'text' => 'Corrélation Sommeil/Fatigue : ' . number_format($correlation, 2)];

            if ($correlation < -0.4) {
                $data['status'] = 'optimal'; // Good that we found a key insight
                $data['points'][] = ['status' => 'optimal', 'text' => 'Forte corrélation négative : Moins vous dormez, plus votre fatigue est élevée.'];
                $data['recommendation'] = 'Le sommeil est une clé majeure de votre récupération. Priorisez des nuits de 7-9h.';
            } else {
                $data['status'] = 'neutral';
                $data['points'][] = ['status' => 'neutral', 'text' => 'Corrélation faible. La qualité de votre sommeil (et d\'autres facteurs) est probablement plus importante que la seule durée.'];
            }
        } else {
            $data['status'] = 'neutral';
            $data['summary'] = 'Données de sommeil insuffisantes pour une analyse d\'impact.';
        }

        return $data;
    }

    protected function getPainHotspotAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $monthlyMetrics = $allMetrics->where('date', '>=', $endDate->copy()->subDays(29));
        $painMetrics = $monthlyMetrics->where('metric_type', MetricType::MORNING_PAIN->value)->filter(fn ($m) => $m->value > 4);

        $data = [
            'title' => 'Analyse des hotspots de douleur (30j)',
            'main_metric' => null,
            'points' => [],
            'recommendation' => null,
        ];

        if ($painMetrics->isEmpty()) {
            $data['status'] = 'optimal';
            $data['summary'] = 'Aucune douleur significative';
            $data['points'][] = ['status' => 'optimal', 'text' => 'Aucune douleur supérieure à 4/10 n\'a été reportée ce mois-ci.'];
            return $data;
        }

        $hotspots = $monthlyMetrics->where('metric_type', MetricType::MORNING_PAIN_LOCATION->value)
            ->whereIn('date', $painMetrics->pluck('date'))
            ->groupBy('value')
            ->map(fn ($g) => $g->count())
            ->sortDesc();
        
        $dominantLocation = $hotspots->keys()->first() ?? 'Non spécifiée';
        
        $data['main_metric'] = [
            'value' => $painMetrics->count(),
            'label' => 'Jours avec douleur > 4',
        ];
        $data['summary'] = "Douleur reportée {$painMetrics->count()} jours. Hotspot : {$dominantLocation}.";
        $data['status'] = 'warning';
        $data['points'][] = ['status' => 'warning', 'text' => "Le hotspot de douleur le plus fréquent est : {$dominantLocation} ({$hotspots->first()} occurrences)."];

        $painTrend = $this->trendsService->calculateMetricEvolutionTrend($painMetrics, MetricType::MORNING_PAIN);
        if ($painTrend['trend'] === 'increasing') {
            $data['status'] = 'high_risk';
            $data['points'][] = ['status' => 'high_risk', 'text' => 'L\'intensité de la douleur dans cette zone est en augmentation.'];
            $data['recommendation'] = 'Consultez un professionnel de santé. Réduisez le travail technique sur cette zone.';
        }

        return $data;
    }
    
    protected function getMenstrualImpactSummary(Athlete $athlete): array
    {
        $summary = $this->menstrualService->deduceMenstrualCyclePhase($athlete);

        $data = [
            'title' => 'Analyse du Cycle Menstruel',
            'main_metric' => null,
            'points' => [],
            'recommendation' => null,
        ];

        if (empty($summary['phase']) || $summary['phase'] === 'Inconnue') {
            $data['status'] = 'neutral';
            $data['summary'] = 'Données du cycle insuffisantes.';
            $data['points'][] = ['status' => 'neutral', 'text' => 'Veuillez renseigner vos informations de cycle pour une analyse personnalisée.'];
            return $data;
        }

        $data['status'] = 'neutral';
        $data['summary'] = "Phase actuelle : {$summary['phase']}";
        $data['main_metric'] = [
            'value' => $summary['days_in_phase'],
            'label' => "Jours dans la phase",
        ];

        $fatigueImpact = $this->menstrualService->compareMetricAcrossPhases($athlete, MetricType::MORNING_GENERAL_FATIGUE);

        if (isset($fatigueImpact['impact']) && $fatigueImpact['impact'] === 'higher') {
            $data['status'] = 'warning';
            $data['points'][] = ['status' => 'warning', 'text' => "Impact notable : La fatigue matinale est en moyenne {$fatigueImpact['difference']} points plus élevée en phase {$fatigueImpact['phase_a']} qu'en phase {$fatigueImpact['phase_b']}."];
            $data['recommendation'] = "Adaptez votre charge d'entraînement et votre récupération en fonction de cette sensibilité accrue.";
        } else {
            $data['status'] = 'optimal';
            $data['points'][] = ['status' => 'optimal', 'text' => 'Bonne stabilité des métriques de récupération dans cette phase.'];
        }
        
        return $data;
    }

    // BIANNUAL ANALYSIS
    protected function generateBiannualAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $startDate = $endDate->copy()->subMonths(6);
        return [
            'long_term_adaptation' => $this->getAdaptationAnalysis($athlete, $allMetrics, $startDate, $endDate),
            'efficiency_gap_analysis' => $this->getEfficiencyGapAnalysis($athlete, $allMetrics, $startDate, $endDate),
            'injury_pattern' => $this->getInjuryPatternAnalysis($athlete, $allMetrics, $endDate),
            'pacing_strategy' => $this->getChargePacingAnalysis($athlete, $endDate),
        ];
    }

    protected function getAdaptationAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $startDate, Carbon $endDate): array
    {
        $sbmHistory = $this->getCalculatedMetricHistory($athlete, CalculatedMetric::SBM, 180, $endDate, $allMetrics);
        $sbmTrend = $this->trendsService->calculateGenericNumericTrend($sbmHistory);
        $hrvHistory = $allMetrics->where('metric_type', MetricType::MORNING_HRV->value)->whereBetween('date', [$startDate, $endDate]);
        $hrvTrend = $this->trendsService->calculateMetricEvolutionTrend($hrvHistory, MetricType::MORNING_HRV);

        $data = [
            'title' => 'Adaptation à long terme (6 mois)',
            'main_metric' => null,
            'points' => [],
            'recommendation' => null,
        ];

        $sbmChange = number_format($sbmTrend['change'] ?? 0, 1);
        $hrvChange = number_format($hrvTrend['change_percentage'] ?? 0, 1);

        $data['points'][] = ['status' => $sbmTrend['trend'] === 'increasing' ? 'optimal' : 'warning', 'text' => "Tendance SBM : {$sbmChange}%"];
        $data['points'][] = ['status' => $hrvTrend['trend'] === 'increasing' ? 'optimal' : 'warning', 'text' => "Tendance VFC : {$hrvChange}%"];

        if ($sbmTrend['trend'] === 'increasing' && $hrvTrend['trend'] === 'increasing') {
            $data['status'] = 'optimal';
            $data['summary'] = 'Adaptation Physique Exceptionnelle';
            $data['points'][] = ['status' => 'optimal', 'text' => 'Votre SBM et votre VFC ont tous deux augmenté, indiquant une excellente tolérance à la charge globale.'];
        } else {
            $data['status'] = 'warning';
            $data['summary'] = 'Tendance Mixte ou en Baisse';
            $data['recommendation'] = 'Une revue de la planification de la charge et des facteurs de stress externes est recommandée pour optimiser l\'adaptation.';
        }
        return $data;
    }

    protected function getEfficiencyGapAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $startDate, Carbon $endDate): array
    {
        $performanceGapMetrics = $allMetrics
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('metric_type', [MetricType::POST_SESSION_PERFORMANCE_FEEL->value, MetricType::POST_SESSION_SESSION_LOAD->value])
            ->groupBy(fn($m) => $m->date->toDateString())
            ->map(function ($group) {
                $perf = $group->firstWhere('metric_type', MetricType::POST_SESSION_PERFORMANCE_FEEL->value)?->value;
                $load = $group->firstWhere('metric_type', MetricType::POST_SESSION_SESSION_LOAD->value)?->value;
                if ($perf === null || $load === null) return null;
                return $perf - $load;
            })->filter();

        $avgGap = $performanceGapMetrics->avg();

        $data = [
            'title' => 'Analyse de l\'efficacité (6 mois)',
            'main_metric' => [
                'value' => number_format($avgGap, 1),
                'label' => 'Perf - Charge',
            ],
            'points' => [],
            'recommendation' => null,
        ];

        if ($avgGap > 1.5) {
            $data['status'] = 'optimal';
            $data['summary'] = 'Efficacité Exceptionnelle';
            $data['points'][] = ['status' => 'optimal', 'text' => 'Excellent retour sur investissement : votre performance perçue est bien supérieure à la charge ressentie.'];
        } elseif ($avgGap < -1.0) {
            $data['status'] = 'warning';
            $data['summary'] = 'Faible Efficacité';
            $data['points'][] = ['status' => 'warning', 'text' => 'Votre performance perçue est faible par rapport à la charge ressentie.'];
            $data['recommendation'] = 'Investiguez les causes potentielles : technique, biologique, nutritionnelle ou sur-fatigue.';
        } else {
            $data['status'] = 'neutral';
            $data['summary'] = 'Efficacité Neutre';
            $data['points'][] = ['status' => 'neutral', 'text' => 'Votre performance perçue est en ligne avec la charge ressentie.'];
        }

        return $data;
    }

    protected function getInjuryPatternAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $cihHistory = $this->getCalculatedMetricHistory($athlete, CalculatedMetric::CIH, 90, $endDate, $allMetrics);
        $painHistory = $allMetrics
            ->where('metric_type', MetricType::MORNING_PAIN->value)
            ->where('date', '>=', $endDate->copy()->subDays(90))
            ->map(fn($m) => (object)['date' => $m->date->toDateString(), 'value' => $m->value]);

        $chargePainCorrelation = $this->trendsService->calculateCorrelationFromCollections($cihHistory, $painHistory);
        
        $data = [
            'title' => 'Analyse des modèles de blessures (3 mois)',
            'main_metric' => null,
            'points' => [],
            'recommendation' => null,
        ];

        if (isset($chargePainCorrelation['correlation'])) {
            $correlation = $chargePainCorrelation['correlation'];
            $data['main_metric'] = [
                'value' => number_format($correlation, 2),
                'label' => 'Corrélation Charge/Douleur',
            ];

            if ($correlation > 0.5) {
                $data['status'] = 'warning';
                $data['summary'] = 'Douleur liée à la charge';
                $data['points'][] = ['status' => 'warning', 'text' => "La douleur est fortement corrélée à l'augmentation de la charge hebdomadaire (CIH)."];
                $data['recommendation'] = 'La gestion de la charge est un levier clé pour prévenir les douleurs.';
            } else {
                $data['status'] = 'neutral';
                $data['summary'] = 'Douleur non liée à la charge';
                $data['points'][] = ['status' => 'neutral', 'text' => "La douleur n'est pas directement liée à l'augmentation de la charge. L'origine est probablement biomécanique ou autre."];
            }
        } else {
            $data['status'] = 'neutral';
            $data['summary'] = 'Données insuffisantes';
            $data['points'][] = ['status' => 'neutral', 'text' => 'Pas assez de données pour analyser la corrélation entre charge et douleur.'];
        }
        return $data;
    }
    
    protected function getChargePacingAnalysis(Athlete $athlete, Carbon $endDate): array
    {
        $cihMetrics = $this->getCalculatedMetricHistory($athlete, CalculatedMetric::CIH, 180, $endDate);
        
        $data = [
            'title' => 'Stratégie de Pacing (6 mois)',
            'main_metric' => null,
            'points' => [],
            'recommendation' => null,
        ];

        if ($cihMetrics->count() < 15) {
            $data['status'] = 'neutral';
            $data['summary'] = 'Données CIH insuffisantes.';
            return $data;
        }

        $values = $cihMetrics->pluck('value');
        $stdDev = $this->calculateStdDev($values);
        $avgCih = $values->avg();
        $cv = ($avgCih > 0) ? ($stdDev / $avgCih) : 0;

        $data['main_metric'] = [
            'value' => number_format($cv * 100, 1) . '%',
            'label' => 'Coeff. de Variation',
        ];

        if ($cv > 0.4) {
            $data['status'] = 'warning';
            $data['summary'] = 'Pacing Erratique';
            $data['points'][] = ['status' => 'warning', 'text' => 'Vous alternez entre des semaines très lourdes et très légères.'];
            $data['recommendation'] = 'Visez une progression de charge plus linéaire pour réduire le risque de blessures par choc de charge.';
        } else {
            $data['status'] = 'optimal';
            $data['summary'] = 'Pacing Adaptatif';
            $data['points'][] = ['status' => 'optimal', 'text' => 'La variation de votre charge est maîtrisée, favorisant une adaptation progressive.'];
        }
        return $data;
    }

    /**
     * Calcule manuellement l'écart-type (standard deviation) d'une collection de nombres.
     */
    private function calculateStdDev(Collection $values): float
    {
        if ($values->count() < 2) {
            return 0.0;
        }

        $average = $values->avg();
        $variance = $values->reduce(function ($carry, $item) use ($average) {
            return $carry + pow($item - $average, 2);
        }, 0) / $values->count();

        return sqrt($variance);
    }


    /**
     * Helper pour récupérer l'historique d'une métrique calculée (non stockée en DB).
     * @param Collection|null $allMetrics Optimisation pour ne pas requêter la DB à chaque fois.
     */
    private function getCalculatedMetricHistory(Athlete $athlete, CalculatedMetric $metric, int $days, Carbon $endDate, ?Collection $allMetrics = null): Collection
    {
        $history = collect();
        $startDate = $endDate->copy()->subDays($days - 1);

        if ($allMetrics === null) {
            $metricsForPeriod = $athlete->metrics()->whereBetween('date', [$startDate, $endDate])->get()->groupBy(fn($m) => $m->date->toDateString());
        } else {
            $metricsForPeriod = $allMetrics->whereBetween('date', [$startDate, $endDate])->groupBy(fn($m) => $m->date->toDateString());
        }

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateString = $date->toDateString();
            $dailyMetrics = $metricsForPeriod->get($dateString, collect());
            $value = null;

            if ($metric === CalculatedMetric::SBM) {
                $value = $this->calculationService->calculateSbmForCollection($dailyMetrics);
            }
            elseif ($metric === CalculatedMetric::CIH) {
                $weekStartDate = $date->copy()->subDays(6);
                $weekMetrics = $allMetrics ? 
                    $allMetrics->whereBetween('date', [$weekStartDate, $date]) :
                    $athlete->metrics()->whereBetween('date', [$weekStartDate, $date])->get();
                
                $value = $weekMetrics->where('metric_type', MetricType::POST_SESSION_SESSION_LOAD->value)->sum('value');
            }
            
            if ($value !== null) {
                $history->push((object)['date' => $dateString, 'value' => $value]);
            }
        }
        return $history;
    }
}
