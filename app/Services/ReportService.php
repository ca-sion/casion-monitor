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
        $narrative = "Félicitations, vous êtes au niveau {$gamification['level']} (Points : {$gamification['points']}). Votre série de saisie est de {$gamification['current_streak']} jours (Record : {$gamification['longest_streak']} jours).";
        
        if (!empty($gamification['new_badges'])) {
            $narrative .= " Nouveau Badge débloqué ! : " . implode(', ', $gamification['new_badges']);
        }

        return ['title' => 'Motivation : votre engagement', 'narrative' => $narrative, 'streak' => $gamification['current_streak']];
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
        // Utiliser la méthode existante qui retourne un statut complet
        $readinessStatusData = $this->readinessService->getAthleteReadinessStatus($athlete, $allMetrics);
        
        $sbmHistory = $this->getCalculatedMetricHistory($athlete, CalculatedMetric::SBM, 7, Carbon::now(), $allMetrics);
        $sbmTrend = $this->trendsService->calculateGenericNumericTrend($sbmHistory);

        $status = $readinessStatusData['level'] ?? 'N/A';
        $score = $readinessStatusData['readiness_score'] ?? 0;
        $mainPenaltyReason = $readinessStatusData['details'][0]['metric_short_label'] ?? $readinessStatusData['message'] ?? 'Facteur inconnu';
        $narrative = "Votre score de readiness est à {$score}/100 ({$status}).";

        if ($status === 'red') {
            $narrative .= " ALERTE ROUGE : Un facteur majeur (`{$mainPenaltyReason}`) impacte votre capacité à performer. Consulter l'entraîneur.";
        } elseif ($status === 'orange' || $status === 'yellow') {
            $trendChange = number_format(abs($sbmTrend['change'] ?? 0), 1);
            $narrative .= " Alerte Modérée : Votre SBM est en baisse de {$trendChange}% sur 7 jours. {$mainPenaltyReason}. Allégez légèrement votre charge aujourd'hui. ";
        } else {
            $narrative .= " Récupération Optimale ! Continuez sur cette lancée. Votre SBM est très stable.";
        }

        return ['title' => 'Statut de Readiness quotidien', 'score' => $score, 'status' => $status, 'narrative' => $narrative, 'details' => $readinessStatusData['details'] ?? []];
    }

    protected function getInconsistencyAlerts(Athlete $athlete, Collection $dailyMetrics): array
    {
        $alerts = $this->alertsService->checkAllAlerts($athlete, $dailyMetrics);
        $inconsistencies = [];

        $sessionLoad = $dailyMetrics->firstWhere('metric_type', MetricType::POST_SESSION_SESSION_LOAD->value)?->value;
        $subjectiveFatigue = $dailyMetrics->firstWhere('metric_type', MetricType::POST_SESSION_SUBJECTIVE_FATIGUE->value)?->value;
        if ($sessionLoad !== null && $subjectiveFatigue !== null && $sessionLoad < 4 && $subjectiveFatigue > 7) {
            $inconsistencies[] = "Charge Faible ({$sessionLoad}/10), Fatigue Élevée ({$subjectiveFatigue}/10). Hypothèse : Fatigue d'origine mentale ou nutrition/hydratation.";
        }
        
        $hrv = $dailyMetrics->firstWhere('metric_type', MetricType::MORNING_HRV->value)?->value;
        $mood = $dailyMetrics->firstWhere('metric_type', MetricType::MORNING_MOOD_WELLBEING->value)?->value;
        if ($hrv !== null && $mood !== null) {
            $hrvAvg = $athlete->metrics()->where('metric_type', MetricType::MORNING_HRV->value)->avg('value');
            if ($hrvAvg > 0 && $hrv < $hrvAvg * 0.90 && $mood > 8) { 
                $inconsistencies[] = "VFC basse ({$hrv}ms), Humeur excellente ({$mood}/10). Hypothèse : Forte motivation masquant la fatigue physiologique.";
            }
        }
        
        $energyLevel = $dailyMetrics->firstWhere('metric_type', MetricType::PRE_SESSION_ENERGY_LEVEL->value)?->value;
        $perfFeel = $dailyMetrics->firstWhere('metric_type', MetricType::POST_SESSION_PERFORMANCE_FEEL->value)?->value;
        if ($energyLevel !== null && $perfFeel !== null && $energyLevel > 8 && $perfFeel < 5) {
             $inconsistencies[] = "Énergie Pré-session élevée ({$energyLevel}/10), Performance Post-session faible ({$perfFeel}/10). Hypothèse : Problème tactique ou technique.";
        }

        $narrative = count($alerts) > 0 ? implode(' / ', array_column($alerts, 'message')) : 'Aucune alerte critique déclenchée.';
        if (count($inconsistencies) > 0) {
            $narrative .= "<br> Points de Réflexion (Incohérences) : " . implode(' / ', $inconsistencies);
        }

        return ['title' => 'Alertes et dépistage d\'incohérences', 'narrative' => $narrative, 'inconsistencies' => $inconsistencies];
    }

    protected function getInterDayCorrelation(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $currentSbm = $this->calculationService->calculateSbmForCollection($allMetrics->where('date', $endDate->toDateString()));
        $narrative = "Votre SBM d'aujourd'hui est de ".number_format($currentSbm, 1).". ";
        
        $sbmHistory = $this->getCalculatedMetricHistory($athlete, CalculatedMetric::SBM, 14, $endDate, $allMetrics);
        $loadHistory = $allMetrics
            ->where('metric_type', MetricType::POST_SESSION_SESSION_LOAD->value)
            ->where('date', '>=', $endDate->copy()->subDays(14))
            ->map(fn($m) => (object)['date' => $m->date->toDateString(), 'value' => $m->value]);

        $sbmHistoryShifted = $sbmHistory->map(fn($s) => (object)['date' => Carbon::parse($s->date)->subDay()->toDateString(), 'value' => $s->value]);

        $correlationData = $this->trendsService->calculateCorrelationFromCollections($loadHistory, $sbmHistoryShifted);

        if (isset($correlationData['correlation']) && $correlationData['correlation'] !== null) {
            if ($correlationData['correlation'] < -0.6) {
                $narrative .= "Forte corrélation négative (".number_format($correlationData['correlation'], 2).") : Leçon : Les charges élevées ont un impact direct sur votre récupération du lendemain. Prévoyez plus de compensation.";
            } else {
                $narrative .= "L'impact direct de la charge d'hier est variable. Votre récupération dépend peut-être davantage du sommeil.";
            }
        } else {
            $narrative .= " (Plus de données requises pour une analyse de corrélation fiable sur 14 jours).";
        }
        
        return ['title' => 'Corrélation J-1 vs J : l\'impact de l\'effort d\'hier', 'narrative' => $narrative];
    }

    protected function getDailyRecommendation(Athlete $athlete, Collection $dailyMetrics): array
    {
        // Utiliser la méthode existante qui retourne un statut complet
        // Note: getAthleteReadinessStatus nécessite toutes les métriques, pas seulement dailyMetrics pour certains calculs.
        // Pour la recommandation quotidienne, nous pouvons passer dailyMetrics si c'est suffisant pour le calcul du niveau.
        // Si des calculs plus larges sont nécessaires, il faudrait passer $allMetrics ici aussi.
        $readinessStatusData = $this->readinessService->getAthleteReadinessStatus($athlete, $dailyMetrics);
        $status = $readinessStatusData['level'] ?? 'N/A';
        $inconsistencies = $this->getInconsistencyAlerts($athlete, $dailyMetrics)['inconsistencies'];
        $sleepDuration = $dailyMetrics->firstWhere('metric_type', MetricType::MORNING_SLEEP_DURATION->value)?->value;
        $legFeel = $dailyMetrics->firstWhere('metric_type', MetricType::PRE_SESSION_LEG_FEEL->value)?->value;

        $recommendation = match ($status) {
            'red' => 'Alerte Rouge. Session Annulée ou Modifiée. Priorité : Récupération Passive/Soins.',
            'orange', 'yellow' => (count($inconsistencies) > 0) 
                        ? 'Alerte Modérée. Réduction de 15% de la charge. Évitez les efforts maximaux.' 
                        : ( (int)$legFeel < 5 ? 'Alerte Modérée. Maintenez le plan mais réduisez l\'intensité si le ressenti des jambes est faible.' : 'Alerte Modérée. Maintenez le plan, mais soyez vigilant.'),
            'green' => ($sleepDuration !== null && $sleepDuration < 7) 
                       ? 'Readiness Optimale. Suivez le plan, mais visez une heure de sommeil en plus.'
                       : 'Readiness Optimale. Feux Vert ! Journée de performance potentielle.',
            default => 'Recommandation N/A. Assurez-vous d\'avoir entré toutes vos métriques matinales.',
        };

        return ['title' => 'Recommandation du Jour', 'narrative' => $recommendation];
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
        $narrative = "Ratio CIH/CPH final : " . number_format($ratioCihCph, 2) . ".";

        if ($ratioCihCph > 1.3) {
            $narrative .= " Surcharge : Vous êtes en zone de surentraînement fonctionnel. Recommandation : Réduction de 20% de la charge du plan pour la semaine prochaine.";
        } elseif ($ratioCihCph > 0 && $ratioCihCph < 0.7) {
            $narrative .= " Sous-charge : La charge perçue est trop faible. Action : Augmentez le RPE des prochaines sessions.";
        } elseif ($ratioCihCph > 0) {
            $narrative .= " Adhésion Optimale : Votre ressenti a parfaitement correspondu au plan (0.8 - 1.3).";
        } else {
            $narrative .= " Données insuffisantes ou plan non trouvé pour calculer l\'adhésion.";
        }

        return ['title' => 'Adhésion charge planifiée (CPH)', 'narrative' => $narrative, 'ratio' => $ratioCihCph];
    }

    protected function getAcwrAnalysis(Athlete $athlete, Carbon $endDate): array
    {
        $acwrData = $this->trendsService->calculateAcwr($athlete, $endDate); 
        $acwr = $acwrData['ratio'] ?? 0;
        $narrative = "Ratio de charge Aiguë:Chronique (ACWR) : " . number_format($acwr, 2) . ".";

        if ($acwr >= 1.5) {
            $narrative .= " 💥 RISQUE ÉLEVÉ DE BLESSURE : Votre charge aiguë est >50% supérieure à votre charge chronique. Recommandation URGENTE : Réduisez la charge de travail de 20% immédiatement.";
        } elseif ($acwr >= 1.3 && $acwr < 1.5) {
            $narrative .= " ⚠️ ZONE D'ALARME : Vous êtes dans la 'Zone Rouge' de tolérance. Idéal pour la surcompensation, mais risqué. Action : Doublez les efforts de récupération.";
        } elseif ($acwr > 0 && $acwr < 0.8) {
            $narrative .= " 📉 CHARGE INSUFFISANTE : Risque de désadaptation. Action : Augmentez l'intensité.";
        } elseif ($acwr > 0) {
            $narrative .= " ✅ ZONE OPTIMALE (0.8 - 1.3) : Progression sûre. Excellent travail.";
        } else {
            $narrative .= " Données insuffisantes pour le calcul.";
        }

        return ['title' => 'Dépistage du risque de surcharge (ACWR)', 'narrative' => $narrative, 'acwr_value' => $acwr];
    }

    protected function getRecoveryDebtAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $sbmHistory30d = $this->getCalculatedMetricHistory($athlete, CalculatedMetric::SBM, 30, $endDate, $allMetrics);
        if ($sbmHistory30d->count() < 10) {
            return ['title' => 'Dette de récupération', 'narrative' => 'Données SBM insuffisantes.'];
        }
        
        $sbmAvg7d = $sbmHistory30d->sortByDesc('date')->take(7)->avg('value');
        $sbmAvg30d = $sbmHistory30d->avg('value');
        
        $diffPercent = ($sbmAvg30d > 0) ? (($sbmAvg7d - $sbmAvg30d) / $sbmAvg30d) * 100 : 0;
        $narrative = "SBM sur 7 jours : ".number_format($sbmAvg7d, 1)." (vs ".number_format($sbmAvg30d, 1)." sur 30 jours). ";

        if ($diffPercent < -5) {
            $narrative .= " 🚨 DETTE DE RÉCUPÉRATION : Votre SBM est en baisse de ".number_format(abs($diffPercent), 1)."%. Action : Prévoir un jour de repos additionnel.";
        } else {
            $narrative .= " ÉQUILIBRE : Votre récupération est stable.";
        }

        return ['title' => 'Dette de récupération (fatigue aiguë vs chronique)', 'narrative' => $narrative];
    }

    protected function getDayPatternsAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $recentMetrics = $allMetrics->where('date', '>=', $endDate->copy()->subWeeks(4));
        if ($recentMetrics->isEmpty()) {
            return ['title' => 'Patterns Jours Clés', 'narrative' => 'Pas assez de données.'];
        }

        $dayAvgPerformance = $recentMetrics->where('metric_type', MetricType::POST_SESSION_PERFORMANCE_FEEL->value)->groupBy(fn ($m) => $m->date->englishDayOfWeek)->map(fn ($g) => $g->avg('value'));
        $dayAvgLegFeel = $recentMetrics->where('metric_type', MetricType::PRE_SESSION_LEG_FEEL->value)->groupBy(fn ($m) => $m->date->englishDayOfWeek)->map(fn ($g) => $g->avg('value'));

        $bestPerfDay = $dayAvgPerformance->sortDesc()->keys()->first();
        $worstLegFeelDay = $dayAvgLegFeel->sort()->keys()->first();
        
        $narrative = [];
        if ($bestPerfDay) $narrative[] = "🏆 Jour de Pic : Vous performez le mieux le {$bestPerfDay}.";
        if ($worstLegFeelDay) $narrative[] = "⚠️ Jour de Sensibilité : Vos jambes sont les plus lourdes le {$worstLegFeelDay}.";
        if (empty($narrative)) $narrative[] = "Votre performance et ressenti sont stables.";

        return ['title' => 'Patterns et jours clés (4 Semaines)', 'narrative' => implode('<br>', $narrative)];
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
        $narrative = "Analyse sur 30 jours. ";
        
        if ($dampingCount === 0) {
            $narrative .= " ✅ Stabilité Menta-Physio : Aucun jour d'Amortissement Psychologique détecté.";
        } else {
            $narrative .= " 🚨 ALERTE SUIVIE : Damping fréquent ({$dampingCount} jours) : Votre moral masque souvent votre fatigue physiologique. C'est un signal précoce de surentraînement. Recommandation : Prenez un jour de repos *mental* complet.";
        }

        return ['title' => 'Dépistage de l\'amortissement psychologique (Damping)', 'narrative' => $narrative, 'damping_days' => $dampingCount];
    }
    
    protected function getSleepImpactAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $correlationData = $this->trendsService->calculateCorrelation($athlete, MetricType::MORNING_SLEEP_DURATION, MetricType::MORNING_GENERAL_FATIGUE, 30);
        $avgDuration = $allMetrics->where('metric_type', MetricType::MORNING_SLEEP_DURATION->value)->where('date', '>=', $endDate->copy()->subDays(29))->avg('value');
        $narrative = "Votre durée moyenne de sommeil est de ".number_format($avgDuration, 1)." heures. ";

        if (isset($correlationData['correlation']) && $correlationData['correlation'] < -0.4) {
            $narrative .= "Forte corrélation négative : Leçon : Le sommeil est la clé de votre récupération! ";
        } else {
            $narrative .= "Corrélation faible. Hypothèse : La qualité de votre sommeil est probablement plus importante que la durée.";
        }

        return ['title' => 'Analyse de l\'impact du sommeil', 'narrative' => $narrative];
    }

    protected function getPainHotspotAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $monthlyMetrics = $allMetrics->where('date', '>=', $endDate->copy()->subDays(29));
        $painMetrics = $monthlyMetrics->where('metric_type', MetricType::MORNING_PAIN->value)->filter(fn ($m) => $m->value > 4);
        if ($painMetrics->isEmpty()) {
            return ['title' => 'Analyse des hotspots de douleur', 'narrative' => 'Aucune douleur significative (> 4/10) reportée.'];
        }

        $hotspots = $monthlyMetrics->where('metric_type', MetricType::MORNING_PAIN_LOCATION->value)->groupBy('value')->map(fn ($g) => $g->count())->sortDesc();
        $dominantLocation = $hotspots->keys()->first() ?? 'Non spécifiée';
        $narrative = "Douleur reportée ".$painMetrics->count()." jours. Le Hotspot le plus fréquent est {$dominantLocation}. ";

        $painTrend = $this->trendsService->calculateMetricEvolutionTrend($painMetrics, MetricType::MORNING_PAIN);
        if ($painTrend['trend'] === 'increasing') {
            $narrative .= " De plus, l\'intensité de la douleur est en augmentation. Signal pour réduire le travail technique sur cette zone.";
        }

        return ['title' => 'Analyse des hotspots de douleur', 'narrative' => $narrative];
    }
    
    protected function getMenstrualImpactSummary(Athlete $athlete): array
    {
        $summary = $this->menstrualService->deduceMenstrualCyclePhase($athlete);

        if (!empty($summary['phase']) && $summary['phase'] !== 'Inconnue') {
            $narrative = "Statut du cycle : {$summary['phase']} (Jours {$summary['days_in_phase']}). ";
            $fatigueImpact = $this->menstrualService->compareMetricAcrossPhases($athlete, MetricType::MORNING_GENERAL_FATIGUE);

            if (isset($fatigueImpact['impact']) && $fatigueImpact['impact'] === 'higher') {
                $narrative .= " Impact : La fatigue matinale est en moyenne {$fatigueImpact['difference']} points plus élevée en phase {$fatigueImpact['phase_a']} qu'en phase {$fatigueImpact['phase_b']}.";
            } else {
                $narrative .= " Impact : Bonne stabilité des métriques de récupération dans cette phase.";
            }
            return ['title' => 'Analyse du Cycle Menstruel', 'narrative' => $narrative, 'cycle_data' => $summary];
        }
        return ['title' => 'Analyse du Cycle Menstruel', 'narrative' => 'Données du cycle menstruel insuffisantes.'];
    }

    // BIANNUAL ANALYSIS
    protected function generateBiannualAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $startDate = $endDate->copy()->subMonths(6);
        return [
            'long_term_adaptation' => $this->getAdaptationAnalysis($athlete, $allMetrics, $startDate, $endDate),
            'efficiency_gap_analysis' => $this->getEfficiencyGapAnalysis($athlete, $allMetrics),
            'injury_pattern' => $this->getInjuryPatternAnalysis($athlete, $allMetrics, $endDate),
            'pacing_strategy' => $this->getChargePacingAnalysis($athlete, $endDate),
        ];
    }

    protected function getAdaptationAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $startDate, Carbon $endDate): array
    {
        $sbmHistory = $this->getCalculatedMetricHistory($athlete, CalculatedMetric::SBM, 180, $endDate, $allMetrics);
        $sbmTrend = $this->trendsService->calculateGenericNumericTrend($sbmHistory);
        $hrvTrend = $this->trendsService->calculateMetricEvolutionTrend($allMetrics->where('metric_type', MetricType::MORNING_HRV->value), MetricType::MORNING_HRV);
        
        $narrative = "Bilan sur 6 mois. ";
        if ($sbmTrend['trend'] === 'increasing' && $hrvTrend['trend'] === 'increasing') {
            $narrative .= "✅ Adaptation Physique Exceptionnelle : Votre SBM et votre VFC ont augmenté. Excellente tolérance à la charge globale.";
        } else {
            $narrative .= " Tendance Mixte ou en Baisse : Une revue de la charge externe et du stress de vie est recommandée.";
        }
        return ['title' => 'Adaptation à long terme', 'narrative' => $narrative];
    }

    protected function getEfficiencyGapAnalysis(Athlete $athlete, Collection $allMetrics): array
    {
        $performanceGapMetrics = $allMetrics
            ->whereIn('metric_type', [MetricType::POST_SESSION_PERFORMANCE_FEEL->value, MetricType::POST_SESSION_SESSION_LOAD->value])
            ->groupBy(fn($m) => $m->date->toDateString())
            ->map(function ($group) {
                $perf = $group->firstWhere('metric_type', MetricType::POST_SESSION_PERFORMANCE_FEEL->value)?->value;
                $load = $group->firstWhere('metric_type', MetricType::POST_SESSION_SESSION_LOAD->value)?->value;
                if ($perf === null || $load === null) return null;
                return $perf - $load;
            })->filter();

        $avgGap = $performanceGapMetrics->avg();
        $narrative = "Analyse de l'efficacité (Performance - Charge) sur 6 mois : " . number_format($avgGap, 1) . " points.";
        
        if ($avgGap > 1.5) $narrative .= " 🌟 EFFICACITÉ EXCEPTIONNELLE : Excellent retour sur investissement pour votre effort.";
        elseif ($avgGap < -1.0) $narrative .= " 🚧 FAIBLE EFFICACITÉ : Le problème est probablement Technique, Biologique ou Nutritionnel.";
        else $narrative .= " Efficacité Neutre.";

        return ['title' => 'Analyse de l\'efficacité', 'narrative' => $narrative, 'average_gap' => $avgGap];
    }

    protected function getInjuryPatternAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $cihHistory = $this->getCalculatedMetricHistory($athlete, CalculatedMetric::CIH, 90, $endDate, $allMetrics);
        $painHistory = $allMetrics
            ->where('metric_type', MetricType::MORNING_PAIN->value)
            ->where('date', '>=', $endDate->copy()->subDays(90))
            ->map(fn($m) => (object)['date' => $m->date->toDateString(), 'value' => $m->value]);

        $chargePainCorrelation = $this->trendsService->calculateCorrelationFromCollections($cihHistory, $painHistory);
        $narrative = "Bilan de la douleur sur 3 mois. ";

        if (isset($chargePainCorrelation['correlation']) && $chargePainCorrelation['correlation'] > 0.5) {
            $narrative .= "La douleur est fortement corrélée (+ ".number_format($chargePainCorrelation['correlation'], 2)." ) à l\'augmentation de la charge hebdomadaire (CIH).";
        } else {
            $narrative .= "La douleur n'est pas directement liée à l'augmentation de la charge. Elle est probablement causée par un facteur biomécanique.";
        }
        return ['title' => 'Analyse des modèles de blessures', 'narrative' => $narrative];
    }
    
    protected function getChargePacingAnalysis(Athlete $athlete, Carbon $endDate): array
    {
        $cihMetrics = $this->getCalculatedMetricHistory($athlete, CalculatedMetric::CIH, 180, $endDate);
        if ($cihMetrics->count() < 15) {
            return ['title' => 'Stratégie de Pacing (6 mois)', 'narrative' => 'Données CIH insuffisantes.'];
        }

        $values = $cihMetrics->pluck('value');
        $stdDev = $this->calculateStdDev($values);
        $avgCih = $values->avg();
        $narrative = "Analyse de la gestion de la charge sur 6 mois. ";

        if ($avgCih > 0 && $stdDev > $avgCih * 0.4) {
            $narrative .= " 🌪️ PACING ERRATIQUE : Vous alternez entre des semaines très lourdes et très légères. Risque : Vulnérable aux blessures par choc de charge.";
        } else {
            $narrative .= " ✅ PACING ADAPTATIF : La variation de charge est maîtrisée.";
        }
        return ['title' => 'Stratégie de pacing et de progression (6 mois)', 'narrative' => $narrative];
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
