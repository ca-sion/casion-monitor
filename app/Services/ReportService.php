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

        // Générer le résumé global après toutes les sections
        $report['global_summary'] = $this->generateGlobalSummary($report['sections']);
        $report['glossary'] = $this->getGlossary();

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
                'label' => 'Série de jours',
            ],
            'summary' => "Félicitations ! Vous avez atteint le niveau {$gamification['level']} avec un total de {$gamification['points']} points.",
            'points' => [
                ['status' => 'optimal', 'text' => "Votre série de saisie actuelle est de {$gamification['current_streak']} jours (Record : {$gamification['longest_streak']} jours). Continuez sur cette lancée !"]
            ],
            'recommendation' => null,
        ];
        
        if (!empty($gamification['new_badges'])) {
            foreach ($gamification['new_badges'] as $badge) {
                $data['points'][] = ['status' => 'optimal', 'text' => "Nouveau badge débloqué : {$badge} !"];
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
            'explanation' => 'Le score de Readiness est comme un bulletin météo de votre corps pour la journée. Il vous indique à quel point vous êtes "prêt" à vous entraîner, en prenant en compte votre sommeil, votre niveau de stress, votre humeur et votre récupération physique. Un score élevé signifie que vous êtes en pleine forme !',
            'status' => $statusMap[$level] ?? 'neutral',
            'main_metric' => [
                'value' => $score,
                'label' => 'Score /100',
                'type' => 'gauge',
                'max' => 100,
                'ranges' => [
                    'high_risk' => [0, 39],
                    'warning' => [40, 69],
                    'optimal' => [70, 100],
                ],
            ],
            'summary' => "Votre score de Readiness est de {$score}/100.",
            'points' => [],
            'recommendation' => null,
            'details' => $readinessStatusData['details'] ?? []
        ];

        if ($level === 'red') {
            $data['summary'] = 'Alerte : Risque Élevé';
            $data['points'][] = ['status' => 'high_risk', 'text' => "Un facteur majeur ({$mainPenaltyReason}) impacte fortement votre capacité à performer aujourd'hui."];
            $data['recommendation'] = "Discutez-en urgemment avec votre entraîneur. Une modification ou un report de la session est très probable.";
        } elseif ($level === 'orange' || $level === 'yellow') {
            $trendChange = number_format(abs($sbmTrend['change'] ?? 0), 1);
            $data['summary'] = 'Attention : Fatigue Modérée';
            $data['points'][] = ['status' => 'warning', 'text' => "Votre état de forme général (SBM) est en baisse de {$trendChange}% sur les 7 derniers jours."];
            $data['points'][] = ['status' => 'warning', 'text' => "Le facteur principal qui vous pénalise est : {$mainPenaltyReason}."];
            $data['recommendation'] = "Allégez légèrement votre charge d'entraînement aujourd'hui. L'écoute de votre corps est la priorité.";
        } else {
            $data['summary'] = 'Tout est au vert !';
            $data['points'][] = ['status' => 'optimal', 'text' => 'Votre état de forme (SBM) est stable et élevé. Vous êtes prêt à performer.'];
            $data['recommendation'] = "Excellente préparation ! Continuez sur cette lancée et donnez le meilleur de vous-même.";
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
            $inconsistencies[] = ['status' => 'warning', 'text' => "Charge d'entraînement faible ({$sessionLoad}/10) mais vous vous sentez très fatigué ({$subjectiveFatigue}/10). Cela peut indiquer une fatigue non liée au sport (stress, travail) ou un besoin nutritionnel."];
        }
        
        $hrv = $dailyMetrics->firstWhere('metric_type', MetricType::MORNING_HRV->value)?->value;
        $mood = $dailyMetrics->firstWhere('metric_type', MetricType::MORNING_MOOD_WELLBEING->value)?->value;
        if ($hrv !== null && $mood !== null) {
            $hrvAvg = $athlete->metrics()->where('metric_type', MetricType::MORNING_HRV->value)->avg('value');
            if ($hrvAvg > 0 && $hrv < $hrvAvg * 0.90 && $mood > 8) { 
                $inconsistencies[] = ['status' => 'warning', 'text' => "Votre corps montre des signes de fatigue (VFC basse : {$hrv}ms) mais votre moral est excellent ({$mood}/10). Votre motivation pourrait masquer un état de fatigue réel."];
            }
        }
        
        $energyLevel = $dailyMetrics->firstWhere('metric_type', MetricType::PRE_SESSION_ENERGY_LEVEL->value)?->value;
        $perfFeel = $dailyMetrics->firstWhere('metric_type', MetricType::POST_SESSION_PERFORMANCE_FEEL->value)?->value;
        if ($energyLevel !== null && $perfFeel !== null && $energyLevel > 8 && $perfFeel < 5) {
             $inconsistencies[] = ['status' => 'warning', 'text' => "Vous vous sentiez plein d'énergie avant la séance ({$energyLevel}/10) mais la performance n'a pas suivi ({$perfFeel}/10). Le problème n'est peut-être pas physique, mais plutôt d'ordre technique ou tactique."];
        }

        $allPoints = array_merge(
            array_map(fn($a) => ['status' => 'high_risk', 'text' => $a['message']], $alerts),
            $inconsistencies
        );

        return [
            'title' => 'Alertes et Incohérences',
            'explanation' => 'Les "incohérences" sont des signaux qui montrent une contradiction entre ce que vous ressentez (par exemple, vous vous sentez en pleine forme) et ce que vos données objectives indiquent (par exemple, votre corps montre des signes de fatigue). Ces alertes sont cruciales pour détecter une fatigue cachée ou des problèmes qui pourraient affecter votre performance.',
            'status' => empty($allPoints) ? 'optimal' : 'warning',
            'main_metric' => null,
            'summary' => empty($allPoints) ? 'Aucun signal faible ou alerte détecté.' : (count($allPoints) . ' point(s) d\'attention aujourd\'hui.'),
            'points' => $allPoints,
            'recommendation' => empty($allPoints) ? null : 'Analysez ces points. Ils peuvent révéler une fatigue cachée ou d\'autres facteurs qui influencent votre performance.'
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
            'explanation' => 'Cette analyse examine le lien entre l\'intensité de votre entraînement d\'hier et votre niveau de récupération aujourd\'hui. En clair : est-ce que vos grosses séances ont un impact direct sur votre forme du lendemain ? Comprendre ce lien vous aide à mieux planifier votre récupération (sommeil, nutrition) après un effort important.',
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
                $data['points'][] = ['status' => 'warning', 'text' => "Le lien est clair : vos grosses séances d'entraînement ont un impact direct et important sur votre récupération du lendemain."];
                $data['recommendation'] = "C'est une information précieuse. Pensez à compenser activement (nutrition, sommeil, repos) après les entraînements intenses pour aider votre corps à récupérer.";
            } else {
                $data['points'][] = ['status' => 'optimal', 'text' => "L'impact de la charge d'hier est modéré. Votre récupération semble aussi dépendre d'autres facteurs importants comme la qualité de votre sommeil, votre nutrition ou votre niveau de stress."];
            }
        } else {
            $data['points'][] = ['status' => 'neutral', 'text' => "Continuez à renseigner vos données chaque jour pour une analyse plus rapide."];
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
            'red' => 'STOP. Priorité absolue à la récupération. La séance d\'aujourd\'hui doit être annulée ou remplacée par des soins (massage, étirements légers).',
            'orange', 'yellow' => 'EASY. Votre corps demande un peu de repos. Réduisez la charge prévue d\'environ 20% et concentrez-vous sur la qualité technique plutôt que sur l\'intensité.',
            'green' => 'GO ! Tous les signaux sont au vert. C\'est une excellente journée pour une séance de haute qualité et pour vous dépasser.',
            default => 'Renseignez vos métriques du matin pour une recommandation personnalisée.',
        };
        
        $summaryText = match ($status) {
            'red' => 'Récupération Nécessaire',
            'orange', 'yellow' => 'Fatigue à Gérer',
            'green' => 'Prêt à Performer',
            default => 'Données Manquantes'
        };

        return [
            'title' => 'Recommandation du Jour',
            'explanation' => 'Cette recommandation est votre guide personnalisé pour la journée. Elle prend en compte toutes vos données (récupération, fatigue, etc.) pour vous dire si vous devriez vous entraîner normalement ("GO !"), alléger votre séance ("EASY"), ou même prendre un repos complet ("STOP"). C\'est un conseil clair pour optimiser votre entraînement et éviter les risques.',
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
            'explanation' => 'Cette analyse compare la charge d\'entraînement que vous avez réellement ressentie (CIH) avec celle que votre entraîneur avait prévue (CPH). Un ratio proche de 1 signifie que vous avez suivi le plan à la lettre. Si le ratio est trop élevé, vous en avez fait plus que prévu ; s\'il est trop bas, vous en avez fait moins. Cela aide à ajuster les futurs entraînements.',
            'main_metric' => [
                'value' => $ratioCihCph > 0 ? number_format($ratioCihCph, 2) : 'N/A',
                'label' => 'Ratio CIH/CPH',
                'type' => 'gauge',
                'max' => 2.0, // Max value for the gauge, can be adjusted
                'ranges' => [
                    'warning' => [0, 0.69], // Sous-charge
                    'optimal' => [0.7, 1.3], // Adhésion optimale
                    'high_risk' => [1.31, 2.0], // Surcharge (using high_risk for consistency with other gauges)
                ],
            ],
            'points' => [],
            'recommendation' => null,
        ];

        if ($ratioCihCph > 1.3) {
            $data['status'] = 'warning';
            $data['summary'] = 'Surcharge d\'entraînement';
            $data['points'][] = ['status' => 'warning', 'text' => 'Votre ressenti de charge a dépassé de plus de 30% ce qui était planifié. Vous vous êtes entraîné plus dur que prévu.'];
            $data['recommendation'] = 'Discutez-en avec votre entraîneur. Une réduction de la charge la semaine prochaine est nécessaire pour une bonne assimilation.';
        } elseif ($ratioCihCph > 0 && $ratioCihCph < 0.7) {
            $data['status'] = 'warning';
            $data['summary'] = 'Sous-charge d\'entraînement';
            $data['points'][] = ['status' => 'warning', 'text' => 'La charge que vous avez ressentie est nettement inférieure à ce qui était planifié.'];
            $data['recommendation'] = 'Augmentez le RPE (Ressenti de l\'Effort) lors des prochaines sessions pour mieux coller à l\'intensité voulue par le plan.';
        } elseif ($ratioCihCph > 0) {
            $data['status'] = 'optimal';
            $data['summary'] = 'Excellent suivi du plan';
            $data['points'][] = ['status' => 'optimal', 'text' => 'Félicitations ! Votre ressenti de l\'effort correspond parfaitement à ce qui était planifié (ratio entre 0.7 et 1.3).'];
        } else {
            $data['status'] = 'neutral';
            $data['summary'] = 'Données manquantes';
            $data['points'][] = ['status' => 'neutral', 'text' => 'Il manque des données de charge ou un plan d\'entraînement pour calculer votre adhésion cette semaine.'];
        }

        return $data;
    }

    protected function getAcwrAnalysis(Athlete $athlete, Carbon $endDate): array
    {
        $acwrData = $this->trendsService->calculateAcwr($athlete, $endDate);
        $acwr = $acwrData['ratio'] ?? 0;

        $data = [
            'title' => 'Dépistage du risque de surcharge (ACWR)',
            'explanation' => 'L\'ACWR (Acute:Chronic Workload Ratio) est un indicateur clé pour prévenir les blessures. Il compare votre charge d\'entraînement de cette semaine (charge aiguë) à votre moyenne des 4 dernières semaines (charge chronique). Si vous augmentez trop vite votre charge (ACWR élevé), le risque de blessure augmente. L\'objectif est de rester dans une "zone idéale" pour progresser en toute sécurité.',
            'main_metric' => [
                'value' => $acwr > 0 ? number_format($acwr, 2) : 'N/A',
                'label' => 'ACWR',
                'type' => 'gauge',
                'max' => 2.0,
                'ranges' => [
                    'low_risk' => [0, 0.79],
                    'optimal' => [0.8, 1.29],
                    'warning' => [1.3, 1.49],
                    'high_risk' => [1.5, 2.0],
                ],
            ],
            'points' => [],
            'recommendation' => null,
        ];

        if ($acwr >= 1.5) {
            $data['status'] = 'high_risk';
            $data['summary'] = 'Risque Élevé de Blessure';
            $data['points'][] = ['status' => 'high_risk', 'text' => 'Vous avez augmenté votre charge de plus de 50% cette semaine. C\'est beaucoup trop, trop vite.'];
            $data['recommendation'] = 'Réduction IMMÉDIATE de la charge de travail. Le risque de blessure est maximal.';
        } elseif ($acwr >= 1.3 && $acwr < 1.5) {
            $data['status'] = 'warning';
            $data['summary'] = 'Zone à Risque';
            $data['points'][] = ['status' => 'warning', 'text' => 'Vous êtes dans la "zone rouge". C\'est une charge très stimulante pour progresser, mais elle demande une récupération parfaite.'];
            $data['recommendation'] = 'Soyez extrêmement vigilant aux signaux de votre corps. Doublez les efforts sur le sommeil et la nutrition pour bien assimiler le travail.';
        } elseif ($acwr > 0 && $acwr < 0.8) {
            $data['status'] = 'low_risk';
            $data['summary'] = 'Risque de Désadaptation';
            $data['points'][] = ['status' => 'low_risk', 'text' => 'Votre charge de travail actuelle est trop faible pour stimuler une progression. Vous risquez de perdre vos acquis.'];
            $data['recommendation'] = 'Il est temps d\'augmenter l\'intensité ou le volume pour relancer une dynamique de progression.';
        } elseif ($acwr > 0) {
            $data['status'] = 'optimal';
            $data['summary'] = 'Progression Idéale';
            $data['points'][] = ['status' => 'optimal', 'text' => 'Votre progression de charge est sûre et efficace (ratio entre 0.8 et 1.3). C\'est parfait !'];
        } else {
            $data['status'] = 'neutral';
            $data['summary'] = 'Données manquantes';
            $data['points'][] = ['status' => 'neutral', 'text' => 'Il manque des données de charge sur les 4 dernières semaines pour calculer l\'ACWR.'];
        }

        return $data;
    }

    protected function getRecoveryDebtAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $sbmHistory30d = $this->getCalculatedMetricHistory($athlete, CalculatedMetric::SBM, 30, $endDate, $allMetrics);
        
        $sbmAvg7d = $sbmHistory30d->where('date', '>=', $endDate->copy()->subDays(6)->toDateString())->avg('value');
        $sbmAvg30d = $sbmHistory30d->avg('value');

        $diffPercent = 0;
        if ($sbmAvg30d > 0) {
            $diffPercent = (($sbmAvg7d - $sbmAvg30d) / $sbmAvg30d) * 100;
        }

        $data = [
            'title' => 'Dette de récupération (fatigue aiguë vs chronique)',
            'explanation' => 'La "dette de récupération" compare votre état de forme récent (moyenne des 7 derniers jours) à votre état de forme habituel (moyenne des 30 derniers jours). Si votre forme récente est nettement plus basse, cela signifie que vous accumulez de la fatigue et que vous ne récupérez pas suffisamment. C\'est un signal pour lever le pied !',
            'main_metric' => [
                'value' => number_format($diffPercent, 1) . '%',
                'label' => 'Tendance SBM 7j vs 30j',
                'type' => 'gauge',
                'min' => -20,
                'max' => 20,
                'ranges' => [
                    'warning' => [-20, -5.1], // Dette de récupération
                    'optimal' => [-5, 5],    // Équilibre maintenu
                    'neutral' => [5.1, 20],  // Tendance positive (pas de dette)
                ],
            ],
            'points' => [],
            'recommendation' => null,
        ];
        $data['points'][] = ['status' => 'neutral', 'text' => "Votre forme moyenne sur 7 jours est de ".number_format($sbmAvg7d, 1)."/100."];
        $data['points'][] = ['status' => 'neutral', 'text' => "Votre forme moyenne sur 30 jours est de ".number_format($sbmAvg30d, 1)."/100."];

        if ($diffPercent < -5) {
            $data['status'] = 'warning';
            $data['summary'] = 'Dette de Récupération';
            $data['points'][] = ['status' => 'warning', 'text' => "Votre état de forme est en baisse de ".number_format(abs($diffPercent), 1)."% cette semaine par rapport à votre moyenne du mois. Vous accumulez de la fatigue."];
            $data['recommendation'] = 'Il est temps de lever le pied. Prévoyez un jour de repos complet ou une séance de récupération très légère (étirements, mobilité).';
        } else {
            $data['status'] = 'optimal';
            $data['summary'] = 'Bon Équilibre';
            $data['points'][] = ['status' => 'optimal', 'text' => 'Votre niveau de récupération à court terme est stable par rapport à votre tendance de fond. C\'est un bon signe d\'assimilation de la charge.'];
        }

        return $data;
    }

    protected function getDayPatternsAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $recentMetrics = $allMetrics->where('date', '>=', $endDate->copy()->subWeeks(4));
        
        $data = [
            'title' => 'Patterns et jours clés (4 Semaines)',
            'explanation' => 'Cette analyse vous aide à identifier vos "jours forts" et "jours faibles" au cours de la semaine, en se basant sur vos performances et ressentis des 4 dernières semaines. L\'objectif est de mieux comprendre quand vous êtes le plus performant ou le plus fatigué, pour adapter votre programme d\'entraînement et optimiser vos séances.',
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
            $data['points'][] = ['status' => 'optimal', 'text' => "Jour de Pic : Le {$bestPerfDay} semble être votre meilleur jour pour performer. Pensez à y placer vos séances les plus intenses."];
        }
        if ($worstLegFeelDay) {
            $data['points'][] = ['status' => 'warning', 'text' => "Jour Sensible : Le {$worstLegFeelDay} est souvent le jour où vos jambes sont les plus lourdes. Idéal pour une journée plus légère ou de récupération."];
        }
        if (empty($data['points'])) {
            $data['points'][] = ['status' => 'neutral', 'text' => 'Votre forme semble stable tout au long de la semaine, sans jour particulièrement fort ou faible.'];
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
            'explanation' => 'Le "Damping" (ou amortissement psychologique) se produit quand votre moral est excellent, mais que votre corps montre des signes de fatigue importants (par exemple, une VFC basse). C\'est un signal d\'alerte précoce de surentraînement : votre motivation vous pousse à ignorer la fatigue physique. Il est important d\'écouter ces signaux pour éviter l\'épuisement.',
            'main_metric' => [
                'value' => $dampingCount,
                'label' => 'Jours de Damping',
            ],
            'points' => [],
            'recommendation' => null,
        ];

        if ($dampingCount === 0) {
            $data['status'] = 'optimal';
            $data['summary'] = 'Excellent Équilibre Physio-Mental';
            $data['points'][] = ['status' => 'optimal', 'text' => "Félicitations, aucun jour d'amortissement psychologique détecté. Votre ressenti et votre état physique sont bien alignés."];
        } else {
            $data['status'] = 'warning';
            $data['summary'] = "Damping détecté {$dampingCount} fois ce mois-ci";
            $data['points'][] = ['status' => 'warning', 'text' => "Attention, votre moral élevé semble parfois masquer une fatigue physique bien réelle. C'est un signal précoce de surentraînement."];
            $data['recommendation'] = "Accordez-vous un jour de repos *mental* complet. Déconnectez du sport pour mieux recharger les batteries, corps et esprit.";
        }

        return $data;
    }
    
    protected function getSleepImpactAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $correlationData = $this->trendsService->calculateCorrelation($athlete, MetricType::MORNING_SLEEP_DURATION, MetricType::MORNING_GENERAL_FATIGUE, 30);
        $avgDuration = $allMetrics->where('metric_type', MetricType::MORNING_SLEEP_DURATION->value)->where('date', '>=', $endDate->copy()->subDays(29))->avg('value');

        $data = [
            'title' => 'Analyse de l\'impact du sommeil (30j)',
            'explanation' => 'Cette analyse étudie le lien entre la durée de votre sommeil et votre niveau de fatigue général sur le dernier mois. Elle vous aide à comprendre si dormir plus longtemps réduit directement votre fatigue et à quel point le sommeil est crucial pour votre récupération et vos performances.',
            'main_metric' => [
                'value' => number_format($avgDuration, 1),
                'label' => 'Heures / nuit',
            ],
            'summary' => 'En moyenne, vous dormez '.number_format($avgDuration, 1).' heures par nuit.',
            'points' => [],
            'recommendation' => null,
        ];

        if (isset($correlationData['correlation'])) {
            $correlation = $correlationData['correlation'];
            $data['points'][] = ['status' => 'neutral', 'text' => 'Corrélation Sommeil/Fatigue : ' . number_format($correlation, 2)];

            if ($correlation < -0.4) {
                $data['status'] = 'optimal'; // Good that we found a key insight
                $data['points'][] = ['status' => 'optimal', 'text' => 'Le lien est prouvé : moins vous dormez, plus vous êtes fatigué.'];
                $data['recommendation'] = 'Le sommeil est une clé majeure de votre performance. Faites des nuits de 7 à 9h une priorité absolue.';
            } else {
                $data['status'] = 'neutral';
                $data['points'][] = ['status' => 'neutral', 'text' => 'La durée de votre sommeil a un impact modéré sur votre fatigue. La *qualité* de votre sommeil est donc probablement un facteur plus important.'];
            }
        } else {
            $data['status'] = 'neutral';
            $data['summary'] = 'Données de sommeil insuffisantes.';
            $data['points'][] = ['status' => 'neutral', 'text' => 'Renseignez votre durée de sommeil chaque matin pour activer cette analyse.'];
        }

        return $data;
    }

    protected function getPainHotspotAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $monthlyMetrics = $allMetrics->where('date', '>=', $endDate->copy()->subDays(29));
        $painMetrics = $monthlyMetrics->where('metric_type', MetricType::MORNING_PAIN->value)->filter(fn ($m) => $m->value > 4);

        $data = [
            'title' => 'Analyse des hotspots de douleur (30j)',
            'explanation' => 'Cette analyse identifie les zones de votre corps où la douleur est la plus fréquente ou la plus intense sur le dernier mois. C\'est un "hotspot" de douleur. Repérer ces zones permet de comprendre si un problème persiste et d\'agir avant qu\'il ne se transforme en blessure plus sérieuse.',
            'main_metric' => null,
            'points' => [],
            'recommendation' => null,
        ];

        if ($painMetrics->isEmpty()) {
            $data['status'] = 'optimal';
            $data['summary'] = 'Aucune Douleur Significative';
            $data['points'][] = ['status' => 'optimal', 'text' => 'Excellente nouvelle ! Aucune douleur supérieure à 4/10 n\'a été signalée ce mois-ci.'];
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
        $data['summary'] = "Douleur signalée {$painMetrics->count()} jours ce mois-ci. La zone la plus touchée est : {$dominantLocation}.";
        $data['status'] = 'warning';
        $data['points'][] = ['status' => 'warning', 'text' => "La zone de douleur la plus fréquente est : {$dominantLocation} ({$hotspots->first()} fois)."];

        $painTrend = $this->trendsService->calculateMetricEvolutionTrend($painMetrics, MetricType::MORNING_PAIN);
        if ($painTrend['trend'] === 'increasing') {
            $data['status'] = 'high_risk';
            $data['points'][] = ['status' => 'high_risk', 'text' => 'Plus inquiétant, l\'intensité de la douleur dans cette zone semble augmenter.'];
            $data['recommendation'] = 'Consultez un professionnel de santé (médecin, physio). C\'est un signal d\'alerte important à ne pas ignorer.';
        }

        return $data;
    }
    
    protected function getMenstrualImpactSummary(Athlete $athlete): array
    {
        $summary = $this->menstrualService->deduceMenstrualCyclePhase($athlete);

        $data = [
            'title' => 'Analyse du Cycle Menstruel',
            'explanation' => 'Cette analyse vous aide à comprendre comment les différentes phases de votre cycle menstruel peuvent influencer votre récupération, votre fatigue et vos performances. L\'objectif est d\'adapter votre entraînement pour qu\'il soit en harmonie avec votre corps, et non contre lui, afin d\'optimiser vos résultats et votre bien-être.',
            'main_metric' => null,
            'points' => [],
            'recommendation' => null,
        ];

        if (empty($summary['phase']) || $summary['phase'] === 'Inconnue') {
            $data['status'] = 'neutral';
            $data['summary'] = 'Personnalisez votre suivi';
            $data['points'][] = ['status' => 'neutral', 'text' => 'Renseignez les informations de votre cycle pour recevoir une analyse 100% personnalisée et adapter votre entraînement.'];
            return $data;
        }

        $data['status'] = 'neutral';
        $data['summary'] = "Vous êtes actuellement en phase : {$summary['phase']}";
        $data['main_metric'] = [
            'value' => $summary['days_in_phase'],
            'label' => "Jours dans la phase",
        ];

        $fatigueImpact = $this->menstrualService->compareMetricAcrossPhases($athlete, MetricType::MORNING_GENERAL_FATIGUE);

        if (isset($fatigueImpact['impact']) && $fatigueImpact['impact'] === 'higher') {
            $data['status'] = 'warning';
            $data['points'][] = ['status' => 'warning', 'text' => "Impact notable : Votre fatigue matinale est en moyenne {$fatigueImpact['difference']} points plus élevée en phase {$fatigueImpact['phase_a']} qu'en phase {$fatigueImpact['phase_b']}."];
            $data['recommendation'] = "C'est une information clé. Pensez à adapter votre charge d'entraînement et à prioriser la récupération durant cette phase plus sensible.";
        } else {
            $data['status'] = 'optimal';
            $data['points'][] = ['status' => 'optimal', 'text' => 'Votre récupération et votre fatigue semblent stables tout au long de votre cycle. C\'est un signe de bon équilibre.'];
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
            'explanation' => 'Cette analyse évalue comment votre corps s\'adapte à votre programme d\'entraînement sur une longue période (6 mois). En regardant l\'évolution de votre forme générale (SBM) et de votre système nerveux (VFC), nous pouvons voir si vous devenez plus fort et plus résilient, ou si la fatigue s\'accumule. C\'est essentiel pour ajuster votre entraînement sur le long terme.',
            'main_metric' => null,
            'points' => [],
            'recommendation' => null,
        ];

        $sbmChange = number_format($sbmTrend['change'] ?? 0, 1);
        $hrvChange = number_format($hrvTrend['change_percentage'] ?? 0, 1);

        $data['points'][] = ['status' => $sbmTrend['trend'] === 'increasing' ? 'optimal' : 'warning', 'text' => "Tendance de votre Forme (SBM) : {$sbmChange}%"];
        $data['points'][] = ['status' => $hrvTrend['trend'] === 'increasing' ? 'optimal' : 'warning', 'text' => "Tendance de votre VFC : {$hrvChange}%"];

        if ($sbmTrend['trend'] === 'increasing' && $hrvTrend['trend'] === 'increasing') {
            $data['status'] = 'optimal';
            $data['summary'] = 'Excellente Adaptation Physique';
            $data['points'][] = ['status' => 'optimal', 'text' => 'Votre corps s\'adapte parfaitement ! Votre forme générale (SBM) et votre système nerveux (VFC) se sont tous deux améliorés sur 6 mois.'];
        } else {
            $data['status'] = 'warning';
            $data['summary'] = 'Adaptation à Améliorer';
            $data['recommendation'] = 'Il est temps de faire le point avec votre entraîneur sur la planification et les facteurs de stress externes (sommeil, travail, etc.) pour optimiser votre progression.';
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
            'explanation' => 'L\'analyse de l\'efficacité mesure votre "retour sur investissement" pour chaque effort. En comparant votre performance perçue à la charge ressentie, nous voyons si vous obtenez de bons résultats avec un effort modéré (bonne efficacité) ou si vous devez forcer beaucoup pour peu de résultats (faible efficacité). Cela peut indiquer une fatigue sous-jacente ou un besoin d\'ajuster votre technique.',
            'main_metric' => [
                'value' => number_format($avgGap, 1),
                'label' => 'Perf - Charge',
                'type' => 'gauge',
                'min' => -5,
                'max' => 5,
                'ranges' => [
                    'warning' => [-5, -1.1], // Faible efficacité
                    'neutral' => [-1, 1.4],  // Efficacité neutre
                    'optimal' => [1.5, 5],   // Efficacité exceptionnelle
                ],
            ],
            'points' => [],
            'recommendation' => null,
        ];

        if ($avgGap > 1.5) {
            $data['status'] = 'optimal';
            $data['summary'] = 'Excellente Efficacité';
            $data['points'][] = ['status' => 'optimal', 'text' => 'Excellent "retour sur investissement" : vous performez à un niveau bien supérieur à l\'effort que vous ressentez.'];
        } elseif ($avgGap < -1.0) {
            $data['status'] = 'warning';
            $data['summary'] = 'Efficacité à Améliorer';
            $data['points'][] = ['status' => 'warning', 'text' => 'Vos performances vous "coûtent" cher en effort. Vous avez l\'impression de forcer beaucoup pour le résultat obtenu.'];
            $data['recommendation'] = 'Il est temps d\'investiguer les causes : la technique est-elle optimale ? La fatigue de fond est-elle trop élevée ? Manquez-vous de "carburant" (nutrition) ?';
        } else {
            $data['status'] = 'neutral';
            $data['summary'] = 'Efficacité Stable';
            $data['points'][] = ['status' => 'neutral', 'text' => 'Votre niveau de performance est bien aligné avec la charge de travail que vous ressentez.'];
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
            'explanation' => 'Cette analyse cherche à comprendre si vos douleurs sont liées à votre volume d\'entraînement sur les 3 derniers mois. En comparant votre charge hebdomadaire (CIH) et vos niveaux de douleur, nous pouvons déterminer si la gestion de la charge est la clé pour éviter les douleurs, ou si leur origine est ailleurs (par exemple, un problème de technique ou d\'équipement).',
            'main_metric' => null,
            'points' => [],
            'recommendation' => null,
        ];

        if (isset($chargePainCorrelation['correlation'])) {
            $correlation = $chargePainCorrelation['correlation'];
            $data['main_metric'] = [
                'value' => number_format($correlation, 2),
                'label' => 'Corrélation Charge/Douleur',
                'type' => 'gauge',
                'min' => -1,
                'max' => 1,
                'ranges' => [
                    'neutral' => [-1, 0.49], // Pas de forte corrélation
                    'warning' => [0.5, 1],   // Corrélation positive
                ],
            ];

            if ($correlation > 0.5) {
                $data['status'] = 'warning';
                $data['summary'] = 'Douleur Liée à la Charge';
                $data['points'][] = ['status' => 'warning', 'text' => "Il y a un lien clair : plus votre charge d'entraînement hebdomadaire augmente, plus la douleur apparaît."];
                $data['recommendation'] = 'La gestion de la charge (éviter les augmentations brutales) est une stratégie clé pour vous permettre de vous entraîner sans douleur.';
            } else {
                $data['status'] = 'neutral';
                $data['summary'] = 'Douleur Non Liée à la Charge';
                $data['points'][] = ['status' => 'neutral', 'text' => "Vos douleurs ne semblent pas directement causées par l'augmentation de la charge. L'origine est probablement ailleurs (technique, posture, équipement...)."];
            }
        } else {
            $data['status'] = 'neutral';
            $data['summary'] = 'Données Insuffisantes';
            $data['points'][] = ['status' => 'neutral', 'text' => 'Renseignez vos données de charge et de douleur plus régulièrement pour activer cette analyse.'];
        }
        return $data;
    }
    
    protected function getChargePacingAnalysis(Athlete $athlete, Carbon $endDate): array
    {
        $cihMetrics = $this->getCalculatedMetricHistory($athlete, CalculatedMetric::CIH, 180, $endDate);
        
        $cihValues = $cihMetrics->pluck('value');
        $averageCih = $cihValues->avg();
        $stdDevCih = $this->calculateStdDev($cihValues);

        $cv = 0;
        if ($averageCih > 0) {
            $cv = $stdDevCih / $averageCih;
        }

        $data = [
            'title' => 'Stratégie de Pacing (6 mois)',
            'explanation' => 'Le "Pacing" analyse la régularité de votre charge d\'entraînement de semaine en semaine sur les 6 derniers mois. Une progression linéaire et contrôlée (faible variation) est idéale. Une variation trop importante, avec des semaines très dures suivies de semaines très faciles, peut augmenter le risque de blessure à cause des "chocs" de charge. L\'objectif est de trouver un rythme stable et efficace.',
            'main_metric' => [
                'value' => number_format($cv * 100, 1) . '%',
                'label' => 'Coeff. de Variation',
                'type' => 'gauge',
                'min' => 0,
                'max' => 1,
                'ranges' => [
                    'optimal' => [0, 0.39],  // Pacing maîtrisé
                    'warning' => [0.4, 1],   // Pacing erratique
                ],
            ],
            'points' => [],
            'recommendation' => null,
        ];

        if ($cv > 0.4) {
            $data['status'] = 'warning';
            $data['summary'] = 'Pacing "Yo-Yo"';
            $data['points'][] = ['status' => 'warning', 'text' => 'Votre charge varie énormément, alternant entre des semaines très lourdes et très légères. Ce "choc" de charge constant est risqué.'];
            $data['recommendation'] = 'Visez une progression plus linéaire et plus douce. Une augmentation progressive est souvent plus efficace et plus sûre sur le long terme.';
        } else {
            $data['status'] = 'optimal';
            $data['summary'] = 'Pacing Maîtrisé';
            $data['points'][] = ['status' => 'optimal', 'text' => 'La variation de votre charge est bien contrôlée. Cela favorise une adaptation solide et durable.'];
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

    protected function generateGlobalSummary(array $sections): array
    {
        $globalStatus = 'optimal';
        $globalSummary = 'Félicitations ! Tous les indicateurs sont au vert. Vous êtes sur la bonne voie pour atteindre vos objectifs.';
        $globalRecommendation = 'Continuez sur cette excellente dynamique !';
        $criticalRecommendations = [];

        // Exclure la section de gamification de l'analyse globale de risque
        $analyzedSections = \Illuminate\Support\Arr::except($sections, 'gamification');

        foreach ($analyzedSections as $section) {
            if (!isset($section['status'])) {
                continue;
            }

            // Priorité aux statuts les plus critiques
            if ($section['status'] === 'high_risk') {
                $globalStatus = 'high_risk';
                $criticalRecommendations[] = $section['recommendation'] ?? $section['summary'];
            } elseif ($section['status'] === 'warning' && $globalStatus !== 'high_risk') {
                $globalStatus = 'warning';
                $criticalRecommendations[] = $section['recommendation'] ?? $section['summary'];
            } elseif ($section['status'] === 'neutral' && $globalStatus !== 'high_risk' && $globalStatus !== 'warning') {
                $globalStatus = 'neutral';
            }
        }

        if ($globalStatus === 'high_risk') {
            $globalSummary = 'Alerte Rouge ! Plusieurs indicateurs critiques nécessitent une attention immédiate.';
            $globalRecommendation = 'Agissez rapidement sur les points suivants : ' . implode(' ', array_unique($criticalRecommendations));
        } elseif ($globalStatus === 'warning') {
            $globalSummary = 'Attention ! Quelques points méritent votre vigilance.';
            $globalRecommendation = 'Soyez attentif aux signaux et considérez les ajustements suivants : ' . implode(' ', array_unique($criticalRecommendations));
        } elseif ($globalStatus === 'neutral') {
            $globalSummary = 'Quelques points à surveiller, mais pas d\'alerte majeure. Votre suivi est en bonne voie.';
            $globalRecommendation = 'Assurez-vous de bien renseigner toutes vos métriques pour une analyse complète.';
        }

        return [
            'status' => $globalStatus,
            'summary' => $globalSummary,
            'recommendation' => $globalRecommendation,
        ];
    }

    /**
     * Returns a glossary of technical terms used in the reports.
     */
    protected function getGlossary(): array
    {
        return [
            'ACWR' => 'Acute:Chronic Workload Ratio. C\'est un indicateur qui compare votre charge d\'entraînement récente (aiguë) à votre charge habituelle (chronique) sur les 4 dernières semaines. Il aide à évaluer le risque de blessure lié à une augmentation trop rapide de la charge.',
            'SBM' => 'Subjective Well-being Module. C\'est un score global de votre état de forme et de bien-être, basé sur vos ressentis matinaux (sommeil, fatigue, humeur, stress, douleurs). Un SBM élevé indique une bonne récupération.',
            'CIH' => 'Charge d\'entraînement Interne Hebdomadaire. C\'est la somme de la charge ressentie pour toutes vos séances d\'entraînement sur une semaine. Elle reflète l\'effort total que votre corps a fourni.',
            'CPH' => 'Charge d\'entraînement Planifiée Hebdomadaire. C\'est la charge d\'entraînement que votre entraîneur avait prévue pour vous sur une semaine. Comparer CIH et CPH permet de voir si vous avez suivi le plan.',
            'Damping' => 'Amortissement psychologique. C\'est un état où votre moral est très bon, mais votre corps montre des signes de fatigue importants (par exemple, une VFC basse). C\'est un signal d\'alerte précoce de surentraînement.',
            'VFC' => 'Variabilité de la Fréquence Cardiaque. C\'est une mesure de la variation du temps entre chaque battement de cœur. Une VFC élevée est généralement un signe de bonne récupération et d\'un système nerveux équilibré, tandis qu\'une VFC basse peut indiquer du stress ou de la fatigue.',
            'RPE' => 'Rating of Perceived Exertion. C\'est une échelle de 1 à 10 pour évaluer subjectivement l\'intensité de votre effort pendant une séance d\'entraînement. 1 étant très facile et 10 étant un effort maximal.',
            'Hotspot de douleur' => 'Zone du corps où la douleur est la plus fréquente ou la plus intense sur une période donnée. Identifier ces hotspots permet de cibler les problèmes persistants.',
            'Pacing' => 'Stratégie de gestion de la régularité de votre charge d\'entraînement. Un bon pacing signifie une progression linéaire et contrôlée, évitant les "chocs" de charge qui peuvent augmenter le risque de blessure.',
        ];
    }
}
