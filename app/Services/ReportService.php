<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Athlete;
use App\Enums\MetricType;
use Illuminate\Support\Collection;
use App\Enums\CalculatedMetricType;

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

    public function __construct(
        MetricCalculationService $calculationService,
        MetricTrendsService $trendsService,
        MetricAlertsService $alertsService,
        MetricReadinessService $readinessService,
        MetricMenstrualService $menstrualService,
    ) {
        $this->calculationService = $calculationService;
        $this->trendsService = $trendsService;
        $this->alertsService = $alertsService;
        $this->readinessService = $readinessService;
        $this->menstrualService = $menstrualService;
    }

    /**
     * Point d'entrée unique. Génère un rapport d'analyse complet.
     */
    public function generateReport(Athlete $athlete, string $periodType, Carbon $endDate): array
    {
        // Fetch a wide range of data to ensure all calculations have enough history.
        $fetchStartDate = $endDate->copy()->subDays(182)->startOfDay();

        $requiredMetrics = $athlete->metrics()
            ->whereBetween('date', [$fetchStartDate, $endDate])
            ->orderBy('date', 'desc')
            ->get();

        $calculatedMetrics = $athlete->calculatedMetrics()
            ->whereBetween('date', [$fetchStartDate, $endDate])
            ->get();

        $report = [
            'athlete_id'  => $athlete->id,
            'period_type' => $periodType,
            'end_date'    => $endDate->toDateString(),
            'sections'    => [],
        ];

        switch ($periodType) {
            case 'daily':
                $report['sections'] = $this->generateDailyAnalysis($athlete, $requiredMetrics, $calculatedMetrics, $endDate);
                break;
            case 'weekly':
                $report['sections'] = $this->generateWeeklyAnalysis($athlete, $requiredMetrics, $calculatedMetrics, $endDate);
                break;
            case 'monthly':
                $report['sections'] = $this->generateMonthlyAnalysis($athlete, $requiredMetrics, $calculatedMetrics, $endDate);
                break;
            case 'biannual':
                $report['sections'] = $this->generateBiannualAnalysis($athlete, $requiredMetrics, $calculatedMetrics, $endDate);
                break;
            default:
                $report['sections']['error'] = ['title' => 'Erreur', 'narrative' => 'Type de rapport non supporté.'];
        }

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

        return ['startDate' => $startDate->subDays($days)->startOfDay(), 'endDate' => $endDate->endOfDay()];
    }

    // DAILY ANALYSIS
    protected function generateDailyAnalysis(Athlete $athlete, Collection $allMetrics, Collection $calculatedMetrics, Carbon $endDate): array
    {
        $dailyMetrics = $allMetrics->where('date', $endDate);

        return [
            'readiness_status'           => $this->getReadinessStatus($athlete, $dailyMetrics, $allMetrics, $calculatedMetrics, $endDate),
            'alerts_and_inconsistencies' => $this->getInconsistencyAlerts($athlete, $dailyMetrics, $allMetrics),
            'j_minus_1_correlation'      => $this->getInterDayCorrelation($athlete, $allMetrics, $calculatedMetrics, $endDate),
            'recommendation'             => $this->getDailyRecommendation($athlete, $dailyMetrics),
        ];
    }

    protected function getReadinessStatus(Athlete $athlete, Collection $dailyMetrics, Collection $allMetrics, Collection $calculatedMetrics, Carbon $endDate): array
    {
        $readinessStatusData = $this->readinessService->getAthleteReadinessStatus($athlete, $allMetrics);

        $sbmHistory = $calculatedMetrics
            ->where('type', CalculatedMetricType::SBM)
            ->where('date', '>=', $endDate->copy()->subDays(7));

        $sbmTrend = $this->trendsService->calculateGenericNumericTrend($sbmHistory);

        $level = $readinessStatusData['level'] ?? 'neutral';
        $score = $readinessStatusData['readiness_score'] ?? 0;
        $mainPenaltyReason = $readinessStatusData['details'][0]['metric_short_label'] ?? $readinessStatusData['message'] ?? 'Facteur inconnu';

        $statusMap = ['red' => 'high_risk', 'orange' => 'warning', 'yellow' => 'warning', 'green' => 'optimal'];

        $data = [
            'title'       => 'Statut de Readiness quotidien',
            'explanation' => 'Le score de Readiness est comme un bulletin météo de votre corps pour la journée. Il vous indique à quel point vous êtes "prêt" à vous entraîner, en prenant en compte votre sommeil, votre niveau de stress, votre humeur et votre récupération physique. Un score élevé signifie que vous êtes en pleine forme !',
            'status'      => $statusMap[$level] ?? 'neutral',
            'main_metric' => [
                'value'  => $score,
                'label'  => 'Score /100',
                'type'   => 'gauge',
                'max'    => 100,
                'ranges' => [
                    'high_risk' => [0, 39],
                    'warning'   => [40, 69],
                    'optimal'   => [70, 100],
                ],
            ],
            'summary'        => "Votre score de Readiness est de {$score}/100.",
            'points'         => [],
            'recommendation' => null,
            'details'        => $readinessStatusData['details'] ?? [],
        ];

        if (($score === 0 || $score == 'n/a') && $level === 'neutral' && empty($readinessStatusData['details'])) {
            $data['status'] = 'neutral';
            $data['summary'] = 'Veuillez renseigner vos métriques pour calculer votre score de Readiness.';
            $data['points'] = [['status' => 'neutral', 'text' => 'Pas assez des données n\'ont été renseignées aujourd\'hui.']];
            $data['recommendation'] = 'Renseignez vos métriques matinales, au moins, pour obtenir une analyse personnalisée de votre état de forme.';

            return $data;
        }

        if ($level === 'red') {
            $data['summary'] = 'Alerte : Risque Élevé';
            $data['points'][] = ['status' => 'high_risk', 'text' => "Un facteur majeur ({$mainPenaltyReason}) impacte fortement votre capacité à performer aujourd'hui."];
            $data['recommendation'] = 'Discutez-en urgemment avec votre entraîneur. Une modification ou un report de la session est très probable.';
        } elseif ($level === 'orange' || $level === 'yellow') {
            $trendChange = number_format(abs($sbmTrend['change'] ?? 0), 1);
            $data['summary'] = 'Attention : Fatigue Modérée';
            $data['points'][] = ['status' => 'warning', 'text' => "Votre état de forme général (SBM) est en baisse de {$trendChange}% sur les 7 derniers jours."];
            $data['points'][] = ['status' => 'warning', 'text' => "Le facteur principal qui vous pénalise est : {$mainPenaltyReason}."];
            $data['recommendation'] = "Allégez légèrement votre charge d'entraînement aujourd'hui. L'écoute de votre corps est la priorité.";
        } else {
            $data['summary'] = 'Tout est au vert !';
            $data['points'][] = ['status' => 'optimal', 'text' => 'Votre état de forme (SBM) est stable et élevé. Vous êtes prêt à performer.'];
            $data['recommendation'] = 'Excellente préparation ! Continuez sur cette lancée et donnez le meilleur de vous-même.';
        }

        return $data;
    }

    protected function getInconsistencyAlerts(Athlete $athlete, Collection $dailyMetrics, Collection $allMetrics): array
    {
        $minimalRelevantMetricTypes = [
            MetricType::MORNING_MOOD_WELLBEING,
            // MetricType::POST_SESSION_SESSION_LOAD,
        ];

        $hasMinimalRelevantMetrics = $dailyMetrics->whereIn('metric_type', $minimalRelevantMetricTypes)->isNotEmpty();

        if (! $hasMinimalRelevantMetrics) {
            return [
                'title'          => 'Alertes et Incohérences',
                'explanation'    => 'Les "incohérences" sont des signaux qui montrent une contradiction entre ce que vous ressentez (par exemple, vous vous sentez en pleine forme) et ce que vos données objectives indiquent. Ces alertes sont cruciales pour détecter une fatigue cachée ou des problèmes qui pourraient affecter votre performance.',
                'status'         => 'neutral',
                'main_metric'    => null,
                'summary'        => 'Données insuffisantes pour l\'analyse des incohérences.',
                'points'         => [['status' => 'neutral', 'text' => 'Veuillez renseigner au moins une métrique matinale et/ou de session pour activer cette analyse.']],
                'recommendation' => 'Renseignez vos métriques post-séance (charge, fatigue, performance) et matinales (Humeur, Fatigue) pour une analyse complète.',
            ];
        }

        $alerts = $this->alertsService->checkAllAlerts($athlete, $dailyMetrics);
        $alerts = collect($alerts)->filter(fn ($alert) => in_array(data_get($alert, 'type'), ['warning', 'danger']))->all();
        $inconsistencies = [];

        $sessionLoad = $dailyMetrics->firstWhere('metric_type', MetricType::POST_SESSION_SESSION_LOAD)?->value;
        $subjectiveFatigue = $dailyMetrics->firstWhere('metric_type', MetricType::POST_SESSION_SUBJECTIVE_FATIGUE)?->value;
        if ($sessionLoad !== null && $subjectiveFatigue !== null && $sessionLoad < 4 && $subjectiveFatigue > 7) {
            $inconsistencies[] = ['status' => 'warning', 'text' => "Charge d\'entraînement faible ({$sessionLoad}/10) mais vous vous sentez très fatigué ({$subjectiveFatigue}/10). Cela peut indiquer une fatigue non liée au sport (stress, travail, sommeil) ou un besoin nutritionnel."];
        }

        $energyLevel = $dailyMetrics->firstWhere('metric_type', MetricType::PRE_SESSION_ENERGY_LEVEL)?->value;
        $perfFeel = $dailyMetrics->firstWhere('metric_type', MetricType::POST_SESSION_PERFORMANCE_FEEL)?->value;
        if ($energyLevel !== null && $perfFeel !== null && $energyLevel > 8 && $perfFeel < 5) {
            $inconsistencies[] = ['status' => 'warning', 'text' => "Vous vous sentiez plein d\'énergie avant la séance ({$energyLevel}/10) mais la performance n\'a pas suivi ({$perfFeel}/10). Le problème n\'est peut-être pas physique, mais plutôt d\'ordre technique, tactique ou lié au pacing."];
        }

        $hrv = $dailyMetrics->firstWhere('metric_type', MetricType::MORNING_HRV)?->value;
        $mood = $dailyMetrics->firstWhere('metric_type', MetricType::MORNING_MOOD_WELLBEING)?->value;

        $hrvDampingAlerted = false;
        if ($hrv !== null && $mood !== null) {
            $hrvAvg = $allMetrics->where('metric_type', MetricType::MORNING_HRV)->avg('value');
            if ($hrvAvg > 0 && $hrv < $hrvAvg * 0.90 && $mood > 8) {
                $inconsistencies[] = ['status' => 'high_risk', 'text' => "Votre corps montre des signes de fatigue (VFC basse : {$hrv}ms) mais votre moral est excellent ({$mood}/10). C\'est un risque de Damping (Amortissement psychologique), où votre motivation masque un état de fatigue réel. Récupération recommandée."];
                $hrvDampingAlerted = true;
            }
        }

        $allPoints = array_merge(
            array_map(fn ($a) => ['status' => 'high_risk', 'text' => $a['message']], $alerts),
            $inconsistencies
        );

        $finalStatus = empty($allPoints) ? 'optimal' : 'warning';
        if ($hrvDampingAlerted) {
            $finalStatus = 'high_risk';
        }

        $finalRecommendation = empty($allPoints) ? null : 'Analysez ces points. Ils peuvent révéler une fatigue cachée ou d\'autres facteurs qui influencent votre performance.';

        if ($hrv === null && $mood !== null && ! $hrvDampingAlerted) {
            $finalRecommendation = ($finalRecommendation ? $finalRecommendation.' ' : '').
                "Astuce Pro : Pour détecter les incohérences les plus fines (comme le Damping), la VFC est cruciale. Si vous n\'avez pas l\'équipement, vous pouvez utiliser un score de Sensation Jambes Matinal à la place pour détecter la fatigue physique.";
        }

        return [
            'title'          => 'Alertes et Incohérences',
            'explanation'    => 'Les "incohérences" sont des signaux qui montrent une contradiction entre ce que vous ressentez (par exemple, vous vous sentez en pleine forme) et ce que vos données objectives indiquent (par exemple, votre corps montre des signes de fatigue). Ces alertes sont cruciales pour détecter une fatigue cachée ou des problèmes qui pourraient affecter votre performance.',
            'status'         => $finalStatus,
            'main_metric'    => null,
            'summary'        => empty($allPoints) ? 'Aucun signal faible ou alerte détecté.' : (count($allPoints).' point(s) d\'attention aujourd\'hui.'),
            'points'         => $allPoints,
            'recommendation' => $finalRecommendation,
        ];
    }

    protected function getInterDayCorrelation(Athlete $athlete, Collection $allMetrics, Collection $calculatedMetrics, Carbon $endDate): array
    {
        $currentSbm = $calculatedMetrics->where('date', $endDate)->firstWhere('type', CalculatedMetricType::SBM)?->value;

        $sbmHistory = $calculatedMetrics
            ->where('type', CalculatedMetricType::SBM)
            ->where('date', '>=', $endDate->copy()->subDays(13));

        $loadHistory = $allMetrics
            ->where('metric_type', MetricType::POST_SESSION_SESSION_LOAD)
            ->where('date', '>=', $endDate->copy()->subDays(14))
            ->map(fn ($m) => (object) ['date' => $m->date->toDateString(), 'value' => $m->value]);

        $sbmHistoryShifted = $sbmHistory->map(fn ($s) => (object) ['date' => Carbon::parse($s->date)->subDay()->toDateString(), 'value' => $s->value]);
        $correlationData = $this->trendsService->calculateCorrelationFromCollections($loadHistory, $sbmHistoryShifted);

        $data = [
            'title'          => 'Impact de l\'entraînement',
            'explanation'    => 'Cette analyse examine le lien entre l\'intensité de votre entraînement d\'hier et votre niveau de récupération aujourd\'hui (Corrélation J-1 vs J ou Lagged Correlation). En clair : est-ce que vos grosses séances ont un impact direct sur votre forme du lendemain ? Comprendre ce lien vous aide à mieux planifier votre récupération (sommeil, nutrition) après un effort important.',
            'status'         => 'neutral',
            'main_metric'    => null,
            'summary'        => "Votre SBM d'aujourd'hui est de ".number_format($currentSbm, 1).'.',
            'points'         => [],
            'recommendation' => null,
        ];

        if (isset($correlationData['correlation']) && $correlationData['correlation'] !== null) {
            $correlation = $correlationData['correlation'];
            $data['main_metric'] = [
                'value' => number_format($correlation, 2),
                'label' => 'Corrélation Charge/SBM',
            ];
            if ($correlation < -0.6) {
                $data['points'][] = ['status' => 'warning', 'text' => "Le lien est clair : vos grosses séances d'entraînement ont un impact direct et important sur votre récupération du lendemain."];
                $data['recommendation'] = "C'est une information précieuse. Pensez à compenser activement (nutrition, sommeil, repos) après les entraînements intenses pour aider votre corps à récupérer.";
            } else {
                $data['points'][] = ['status' => 'optimal', 'text' => "L'impact de la charge d'hier est modéré. Votre récupération semble aussi dépendre d'autres facteurs importants comme la qualité de votre sommeil, votre nutrition ou votre niveau de stress."];
            }
        } else {
            $sbmDates = $sbmHistoryShifted->pluck('date')->unique();
            $loadDates = $loadHistory->pluck('date')->unique();
            $validDaysCount = $sbmDates->intersect($loadDates)->count();

            $data['points'][] = ['status' => 'neutral', 'text' => "5 jours de données Charge/SBM sont nécessaires (actuellement {$validDaysCount}/5). Renseignez vos données pour activer cette analyse personnalisée."];
        }

        return $data;
    }

    protected function getDailyRecommendation(Athlete $athlete, Collection $dailyMetrics): array
    {
        $readinessStatusData = $this->readinessService->getAthleteReadinessStatus($athlete, $dailyMetrics);
        $status = $readinessStatusData['level'] ?? 'neutral';

        $statusMap = ['red' => 'high_risk', 'orange' => 'warning', 'yellow' => 'warning', 'green' => 'optimal'];

        $recommendationText = match ($status) {
            'red' => 'STOP. Priorité absolue à la récupération. La séance d\'aujourd\'hui doit être annulée ou remplacée par des soins (massage, étirements légers).',
            'orange', 'yellow' => 'EASY. Votre corps demande un peu de repos. Réduisez la charge prévue d\'environ 20% et concentrez-vous sur la qualité technique plutôt que sur l\'intensité.',
            'green' => 'GO ! Tous les signaux sont au vert. C\'est une excellente journée pour une séance de haute qualité et pour vous dépasser.',
            default => 'Renseignez vos métriques pour une recommandation personnalisée.',
        };

        $summaryText = match ($status) {
            'red' => 'Récupération Nécessaire',
            'orange', 'yellow' => 'Fatigue à Gérer',
            'green' => 'Prêt à Performer',
            default => 'Données Manquantes'
        };

        $data = [
            'title'          => 'Recommandation du Jour',
            'explanation'    => 'Cette recommandation est votre guide personnalisé pour la journée. Elle prend en compte toutes vos données (récupération, fatigue, etc.) pour vous dire si vous devriez vous entraîner normalement ("GO !"), alléger votre séance ("EASY"), ou même prendre un repos complet ("STOP"). C\'est un conseil clair pour optimiser votre entraînement et éviter les risques.',
            'status'         => $statusMap[$status] ?? 'neutral',
            'main_metric'    => null,
            'summary'        => $summaryText,
            'points'         => [],
            'recommendation' => $recommendationText,
        ];

        if ($status === 'neutral' && ($readinessStatusData['readiness_score'] ?? 0) === 0 && empty($readinessStatusData['details'])) {
            $data['points'][] = ['status' => 'neutral', 'text' => 'La recommandation est générique car les métriques matinales nécessaires au calcul de votre Readiness sont manquantes.'];
        }

        return $data;
    }

    // WEEKLY ANALYSIS
    protected function generateWeeklyAnalysis(Athlete $athlete, Collection $allMetrics, Collection $calculatedMetrics, Carbon $endDate): array
    {
        return [
            'load_adherence'       => $this->getLoadAdherenceAnalysis($athlete, $calculatedMetrics, $endDate),
            'acwr_risk_assessment' => $this->getAcwrAnalysis($athlete, $calculatedMetrics, $endDate),
            'recovery_debt'        => $this->getRecoveryDebtAnalysis($athlete, $calculatedMetrics, $endDate),
            'day_patterns'         => $this->getDayPatternsAnalysis($athlete, $allMetrics, $endDate),
        ];
    }

    protected function getLoadAdherenceAnalysis(Athlete $athlete, Collection $calculatedMetrics, Carbon $endDate): array
    {
        $ratioCihCph = $calculatedMetrics->where('date', $endDate)->firstWhere('type', CalculatedMetricType::RATIO_CIH_CPH)?->value;

        $data = [
            'title'       => 'Adhésion charge planifiée (CPH)',
            'explanation' => 'Cette analyse compare la charge d\'entraînement que vous avez réellement ressentie (CIH) avec celle que votre entraîneur avait prévue (CPH). Un ratio proche de 1 signifie que vous avez suivi le plan à la lettre. Si le ratio est trop élevé, vous en avez fait plus que prévu ; s\'il est trop bas, vous en avez fait moins. Cela aide à ajuster les futurs entraînements.',
            'main_metric' => [
                'value'  => $ratioCihCph > 0 ? number_format($ratioCihCph, 2) : 'n/a',
                'label'  => 'Ratio CIH/CPH',
                'type'   => 'gauge',
                'max'    => 2.0,
                'ranges' => [
                    'warning'   => [0, 0.69],
                    'optimal'   => [0.7, 1.3],
                    'high_risk' => [1.31, 2.0],
                ],
            ],
            'points'         => [],
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
            $data['summary'] = 'Données insuffisantes pour l\'analyse d\'adhésion';
            $data['points'][] = ['status' => 'neutral', 'text' => 'Il manque des données de charge (CIH) ou un plan d\'entraînement (CPH) pour calculer votre adhésion cette semaine.'];
            $data['recommendation'] = 'Assurez-vous de renseigner vos métriques de charge post-séance et qu\'un plan d\'entraînement est assigné.';
        }

        return $data;
    }

    protected function getAcwrAnalysis(Athlete $athlete, Collection $calculatedMetrics, Carbon $endDate): array
    {
        $acwr = $calculatedMetrics->where('date', $endDate)->firstWhere('type', CalculatedMetricType::ACWR)?->value;

        $data = [
            'title'       => 'Dépistage du risque de surcharge (ACWR)',
            'explanation' => 'L\'ACWR (Acute:Chronic Workload Ratio) est un indicateur clé pour prévenir les blessures. Il compare votre charge d\'entraînement de cette semaine (charge aiguë) à votre moyenne des 4 dernières semaines (charge chronique). Si vous augmentez trop vite votre charge (ACWR élevé), le risque de blessure augmente. L\'objectif est de rester dans une "zone idéale" pour progresser en toute sécurité.',
            'main_metric' => [
                'value'  => $acwr > 0 ? number_format($acwr, 2) : 'n/a',
                'label'  => 'ACWR',
                'type'   => 'gauge',
                'max'    => 2.0,
                'ranges' => [
                    'low_risk'  => [0, 0.79],
                    'optimal'   => [0.8, 1.29],
                    'warning'   => [1.3, 1.49],
                    'high_risk' => [1.5, 2.0],
                ],
            ],
            'points'         => [],
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
            $data['points'][] = ['status' => 'neutral', 'text' => 'Il manque des données de charge sur les 4 dernières semaines pour calculer l\'ACWR. (Historique de 28 jours de charge nécessaire).'];
        }

        return $data;
    }

    protected function getRecoveryDebtAnalysis(Athlete $athlete, Collection $calculatedMetrics, Carbon $endDate): array
    {
        $sbmHistory30d = $calculatedMetrics
            ->where('type', CalculatedMetricType::SBM)
            ->where('date', '>=', $endDate->copy()->subDays(29));

        $sbmAvg7d = $sbmHistory30d->where('date', '>=', $endDate->copy()->subDays(6))->avg('value');
        $sbmAvg30d = $sbmHistory30d->avg('value');

        $diffPercent = 0;
        if ($sbmAvg30d > 0) {
            $diffPercent = (($sbmAvg7d - $sbmAvg30d) / $sbmAvg30d) * 100;
        }

        $data = [
            'title'       => 'Dette de récupération (fatigue aiguë vs chronique)',
            'explanation' => 'La "dette de récupération" compare votre état de forme récent (moyenne des 7 derniers jours) à votre état de forme habituel (moyenne des 30 derniers jours). Si votre forme récente est nettement plus basse, cela signifie que vous accumulez de la fatigue et que vous ne récupérez pas suffisamment. C\'est un signal pour lever le pied !',
            'main_metric' => [
                'value'  => number_format($diffPercent, 1).'%',
                'label'  => 'Tendance SBM 7j vs 30j',
                'type'   => 'gauge',
                'min'    => -20,
                'max'    => 20,
                'ranges' => [
                    'warning' => [-20, -5.1],
                    'optimal' => [-5, 5],
                    'neutral' => [5.1, 20],
                ],
            ],
            'points'         => [],
            'recommendation' => null,
        ];
        $data['points'][] = ['status' => 'neutral', 'text' => 'Votre forme moyenne sur 7 jours est de '.number_format($sbmAvg7d, 1).'/10.'];
        $data['points'][] = ['status' => 'neutral', 'text' => 'Votre forme moyenne sur 30 jours est de '.number_format($sbmAvg30d, 1).'/10.'];

        if ($diffPercent < -5) {
            $data['status'] = 'warning';
            $data['summary'] = 'Dette de Récupération';
            $data['points'][] = ['status' => 'warning', 'text' => 'Votre état de forme est en baisse de '.number_format(abs($diffPercent), 1).'% cette semaine par rapport à votre moyenne du mois. Vous accumulez de la fatigue.'];
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
            'title'          => 'Patterns et jours clés',
            'explanation'    => 'Cette analyse vous aide à identifier vos "jours forts" et "jours faibles" au cours de la semaine, en se basant sur vos performances et ressentis des 4 dernières semaines. L\'objectif est de mieux comprendre quand vous êtes le plus performant ou le plus fatigué, pour adapter votre programme d\'entraînement et optimiser vos séances.',
            'status'         => 'neutral',
            'main_metric'    => null,
            'summary'        => 'Analyse des tendances hebdomadaires.',
            'points'         => [],
            'recommendation' => null,
        ];

        if ($recentMetrics->count() < 10) {
            $data['summary'] = 'Données insuffisantes';
            $data['points'][] = ['status' => 'neutral', 'text' => 'Pas assez de données sur les 4 dernières semaines pour identifier des tendances fiables.'];

            return $data;
        }

        $dayAvgPerformance = $recentMetrics->where('metric_type', MetricType::POST_SESSION_PERFORMANCE_FEEL)->groupBy(fn ($m) => $m->date->locale('fr_CH')->dayName)->map(fn ($g) => $g->avg('value'));
        $dayAvgLegFeel = $recentMetrics->where('metric_type', MetricType::PRE_SESSION_LEG_FEEL)->groupBy(fn ($m) => $m->date->locale('fr_CH')->dayName)->map(fn ($g) => $g->avg('value'));

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
    protected function generateMonthlyAnalysis(Athlete $athlete, Collection $allMetrics, Collection $calculatedMetrics, Carbon $endDate): array
    {
        $startDate = $endDate->copy()->subDays(29);
        $sections = [
            'damping_summary' => $this->getDampingSummary($athlete, $startDate, $endDate),
            'sleep_impact'    => $this->getSleepImpactAnalysis($athlete, $allMetrics, $endDate),
            'pain_hotspot'    => $this->getPainHotspotAnalysis($athlete, $allMetrics, $endDate),
        ];

        if ($athlete->gender == 'w') {
            $sections['menstrual_summary'] = $this->getMenstrualImpactSummary($athlete);
        }

        return $sections;
    }

    protected function getDampingSummary(Athlete $athlete, Carbon $startDate, Carbon $endDate): array
    {
        $dampingCount = $this->trendsService->getDampingCount($athlete, $startDate, $endDate);

        $data = [
            'title'       => 'Dépistage de l\'amortissement psychologique (Damping)',
            'explanation' => 'Le "Damping" (ou amortissement psychologique) se produit quand votre moral est excellent, mais que votre corps montre des signes de fatigue importants (par exemple, une VFC basse). C\'est un signal d\'alerte précoce de surentraînement : votre motivation vous pousse à ignorer la fatigue physique. Il est important d\'écouter ces signaux pour éviter l\'épuisement.',
            'main_metric' => [
                'value' => $dampingCount,
                'label' => 'Jours de Damping',
            ],
            'points'         => [],
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
            $data['recommendation'] = 'Accordez-vous un jour de repos *mental* complet. Déconnectez du sport pour mieux recharger les batteries, corps et esprit.';
        }

        return $data;
    }

    protected function getSleepImpactAnalysis(Athlete $athlete, Collection $allMetrics, Carbon $endDate): array
    {
        $correlationData = $this->trendsService->calculateCorrelation($athlete, MetricType::MORNING_SLEEP_DURATION, MetricType::MORNING_GENERAL_FATIGUE, 30);
        $avgDuration = $allMetrics->where('metric_type', MetricType::MORNING_SLEEP_DURATION)->where('date', '>=', $endDate->copy()->subDays(29))->avg('value');

        $data = [
            'title'       => 'Analyse de l\'impact du sommeil',
            'explanation' => 'Cette analyse étudie le lien entre la durée de votre sommeil et votre niveau de fatigue général sur le dernier mois. Elle vous aide à comprendre si dormir plus longtemps réduit directement votre fatigue et à quel point le sommeil est crucial pour votre récupération et vos performances.',
            'main_metric' => [
                'value' => number_format($avgDuration, 1),
                'label' => 'Heures / nuit',
            ],
            'summary'        => 'En moyenne, vous dormez '.number_format($avgDuration, 1).' heures par nuit.',
            'points'         => [],
            'recommendation' => null,
        ];

        if (isset($correlationData['correlation'])) {
            $correlation = $correlationData['correlation'];
            $data['points'][] = ['status' => 'neutral', 'text' => 'Corrélation Sommeil/Fatigue : '.number_format($correlation, 2)];

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
            'title'          => 'Analyse des hotspots de douleur',
            'explanation'    => 'Cette analyse identifie les zones de votre corps où la douleur est la plus fréquente ou la plus intense sur le dernier mois. C\'est un "hotspot" de douleur. Repérer ces zones permet de comprendre si un problème persiste et d\'agir avant qu\'il ne se transforme en blessure plus sérieuse.',
            'main_metric'    => null,
            'points'         => [],
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
        $currentPhaseSummary = $this->menstrualService->deduceMenstrualCyclePhase($athlete);
        $currentPhase = $currentPhaseSummary['phase'];

        $fatigueImpact = $this->menstrualService->compareMetricAcrossPhases($athlete, MetricType::MORNING_GENERAL_FATIGUE);
        $perfImpact = $this->menstrualService->compareMetricAcrossPhases($athlete, MetricType::POST_SESSION_PERFORMANCE_FEEL);
        $longTermTrend = $this->menstrualService->getLongTermPhaseTrend($athlete, MetricType::MORNING_GENERAL_FATIGUE);
        $phaseRec = $this->menstrualService->getPhaseSpecificRecommendation($athlete, $currentPhase);

        $data = [
            'title'          => 'Analyse du Cycle Menstruel & Adaptation',
            'explanation'    => 'Cette analyse personnalisée révèle comment chaque phase de votre cycle influence votre corps. Le but et de vous aider à adapter votre charge d\'entraînement pour optimiser les performances et minimiser les risques. Ceci est votre clé pour une progression en harmonie avec votre biologie.',
            'status'         => 'neutral',
            'summary'        => "Vous êtes actuellement en phase : {$currentPhase}.",
            'main_metric'    => ['value' => number_format($currentPhaseSummary['days_in_phase'], 0), 'label' => 'Jours dans la phase'],
            'points'         => [],
            'recommendation' => null,
        ];

        if ($currentPhase === 'Inconnue') {
            $data['summary'] .= ' (Données manquantes)';
            $data['recommendation'] = $phaseRec['justification'];

            return $data;
        }

        $data['status'] = 'neutral';

        if ($fatigueImpact['impact'] === 'higher') {
            $data['points'][] = ['status' => 'warning', 'text' => "Fatigue en Lutéale : Impact notable. Votre fatigue est en moyenne {$fatigueImpact['difference']} points plus élevée en phase Lutéale qu'en phase Folliculaire. C'est le signal pour potentiellement lever le pied et se concentrer sur la récupération."];
        } elseif ($fatigueImpact['impact'] === 'stable') {
            $data['points'][] = ['status' => 'optimal', 'text' => "Stabilité de la Fatigue : Aucune différence significative n'est détectée. Les règles n'ont pas d'influence majeure sur votre récupération perçue."];
        }

        if ($perfImpact['impact'] === 'lower') {
            $data['points'][] = ['status' => 'warning', 'text' => "Performance en Lutéale : Efficacité en baisse. Votre ressenti de performance est en moyenne {$perfImpact['difference']} points plus faible. Utilisez cette phase pour le travail technique ou les séances de faible intensité (endurance)."];
        }

        if ($longTermTrend['trend'] === 'worsening') {
            $data['status'] = 'high_risk';
            $data['points'][] = ['status' => 'high_risk', 'text' => "Tendance sur 6 mois : AGGRAVATION. L'écart de fatigue Lutéale/Folliculaire a augmenté de {$longTermTrend['change']} points. C'est un signe de désadaptation à la charge sur le long terme. Une pause active (micro-cycle de récupération) est fortement recommandée."];
        } elseif ($longTermTrend['trend'] === 'improving') {
            $data['points'][] = ['status' => 'optimal', 'text' => "Tendance sur 6 mois : AMÉLIORATION. L'impact de votre cycle sur votre fatigue a diminué, preuve que votre stratégie d'adaptation fonctionne. Continuez ainsi !"];
        } else {
            $data['points'][] = ['status' => 'neutral', 'text' => "Tendance sur 6 mois : Stable. L'impact de votre cycle est constant. Continuez à ajuster vos séances phase par phase."];
        }

        $data['recommendation'] = "Recommandation pour cette phase ({$currentPhase}) : {$phaseRec['action']}. {$phaseRec['justification']}";
        $data['status'] = match (true) {
            $data['status'] === 'high_risk'     => 'high_risk',
            $phaseRec['status'] === 'high_risk' => 'high_risk',
            $phaseRec['status'] === 'warning'   => 'warning',
            default                             => 'optimal',
        };

        return $data;
    }

    // BIANNUAL ANALYSIS
    protected function generateBiannualAnalysis(Athlete $athlete, Collection $allMetrics, Collection $calculatedMetrics, Carbon $endDate): array
    {
        $startDate = $endDate->copy()->subMonths(6);

        return [
            'long_term_adaptation'    => $this->getAdaptationAnalysis($athlete, $allMetrics, $calculatedMetrics, $startDate, $endDate),
            'efficiency_gap_analysis' => $this->getEfficiencyGapAnalysis($athlete, $allMetrics, $startDate, $endDate),
            'injury_pattern'          => $this->getInjuryPatternAnalysis($athlete, $allMetrics, $calculatedMetrics, $endDate),
            'pacing_strategy'         => $this->getChargePacingAnalysis($athlete, $calculatedMetrics, $endDate),
        ];
    }

    protected function getAdaptationAnalysis(Athlete $athlete, Collection $allMetrics, Collection $calculatedMetrics, Carbon $startDate, Carbon $endDate): array
    {
        $sbmHistory = $calculatedMetrics->where('type', CalculatedMetricType::SBM);
        $sbmTrend = $this->trendsService->calculateGenericNumericTrend($sbmHistory);
        $hrvHistory = $allMetrics->where('metric_type', MetricType::MORNING_HRV->value)->whereBetween('date', [$startDate, $endDate]);
        $hrvTrend = $this->trendsService->calculateMetricEvolutionTrend($hrvHistory, MetricType::MORNING_HRV);

        $data = [
            'title'          => 'Adaptation à long terme',
            'explanation'    => 'Cette analyse évalue comment votre corps s\'adapte à votre programme d\'entraînement sur une longue période (6 mois). En regardant l\'évolution de votre forme générale (SBM) et de votre système nerveux (VFC), nous pouvons voir si vous devenez plus fort et plus résilient, ou si la fatigue s\'accumule. C\'est essentiel pour ajuster votre entraînement sur le long terme.',
            'main_metric'    => null,
            'points'         => [],
            'recommendation' => null,
        ];

        $sbmChange = number_format($sbmTrend['change'] ?? 0, 1);
        $hrvChange = number_format($hrvTrend['change'] ?? 0, 1);

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
            ->whereIn('metric_type', [MetricType::POST_SESSION_PERFORMANCE_FEEL, MetricType::POST_SESSION_SESSION_LOAD])
            ->groupBy(fn ($m) => $m->date->toDateString())
            ->map(function ($group) {
                $perf = $group->firstWhere('metric_type', MetricType::POST_SESSION_PERFORMANCE_FEEL)?->value;
                $load = $group->firstWhere('metric_type', MetricType::POST_SESSION_SESSION_LOAD)?->value;
                if ($perf === null || $load === null) {
                    return null;
                }

                return $perf - $load;
            })->filter();

        $avgGap = $performanceGapMetrics->avg();

        $data = [
            'title'       => 'Analyse de l\'efficacité',
            'explanation' => 'L\'analyse de l\'efficacité mesure votre "retour sur investissement" pour chaque effort (6 mois). En comparant votre performance perçue à la charge ressentie, nous voyons si vous obtenez de bons résultats avec un effort modéré (bonne efficacité) ou si vous devez forcer beaucoup pour peu de résultats (faible efficacité). Cela peut indiquer une fatigue sous-jacente ou un besoin d\'ajuster votre technique.',
            'main_metric' => [
                'value'  => number_format($avgGap, 1),
                'label'  => 'Perf - Charge',
                'type'   => 'gauge',
                'min'    => -5,
                'max'    => 5,
                'ranges' => [
                    'warning' => [-5, -1.1],
                    'neutral' => [-1, 1.4],
                    'optimal' => [1.5, 5],
                ],
            ],
            'points'         => [],
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

    protected function getInjuryPatternAnalysis(Athlete $athlete, Collection $allMetrics, Collection $calculatedMetrics, Carbon $endDate): array
    {
        $cihHistory = $calculatedMetrics
            ->where('type', CalculatedMetricType::CIH)
            ->where('date', '>=', $endDate->copy()->subDays(89));

        $painHistory = $allMetrics
            ->where('metric_type', MetricType::MORNING_PAIN)
            ->where('date', '>=', $endDate->copy()->subDays(90))
            ->map(fn ($m) => (object) ['date' => $m->date->toDateString(), 'value' => $m->value]);

        $chargePainCorrelation = $this->trendsService->calculateCorrelationFromCollections($cihHistory, $painHistory);

        $data = [
            'title'          => 'Analyse des modèles de blessures',
            'explanation'    => 'Cette analyse cherche à comprendre si vos douleurs sont liées à votre volume d\'entraînement sur les 3 derniers mois. En comparant votre charge hebdomadaire (CIH) et vos niveaux de douleur, nous pouvons déterminer si la gestion de la charge est la clé pour éviter les douleurs, ou si leur origine est ailleurs (par exemple, un problème de technique ou d\'équipement).',
            'main_metric'    => null,
            'points'         => [],
            'recommendation' => null,
        ];

        if (isset($chargePainCorrelation['correlation'])) {
            $correlation = $chargePainCorrelation['correlation'];
            $data['main_metric'] = [
                'value'  => number_format($correlation, 2),
                'label'  => 'Corrélation Charge/Douleur',
                'type'   => 'gauge',
                'min'    => -1,
                'max'    => 1,
                'ranges' => [
                    'neutral' => [-1, 0.49],
                    'warning' => [0.5, 1],
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

    protected function getChargePacingAnalysis(Athlete $athlete, Collection $calculatedMetrics, Carbon $endDate): array
    {
        $startDate = $endDate->copy()->subMonths(6);

        $cihMetrics = $calculatedMetrics
            ->where('type', CalculatedMetricType::CIH_NORMALIZED)
            ->whereBetween('date', [$startDate, $endDate])
            ->pluck('value')
            ->filter(fn ($v) => $v > 0);

        $averageCih = $cihMetrics->avg();
        $stdDevCih = $this->calculateStdDev($cihMetrics);

        $cv = 0;
        if ($averageCih > 0) {
            $cv = $stdDevCih / $averageCih;
        }

        $data = [
            'title'       => 'Stratégie de Pacing',
            'explanation' => 'Le "Pacing" analyse la régularité de votre charge d\'entraînement de semaine en semaine sur les 6 derniers mois. Une progression linéaire et contrôlée (faible variation) est idéale. Une variation trop importante, avec des semaines très dures suivies de semaines très faciles, peut augmenter le risque de blessure à cause des "chocs" de charge. L\'objectif est de trouver un rythme stable et efficace.',
            'main_metric' => [
                'value'  => number_format($cv * 100, 1).'%',
                'label'  => 'Coeff. de variation',
                'type'   => 'gauge',
                'min'    => 0,
                'max'    => 100,
                'ranges' => [
                    'optimal' => [0, 39],
                    'warning' => [40, 100],
                ],
            ],
            'points'         => [],
            'recommendation' => null,
        ];

        if ($cv > 0.4) {
            $data['status'] = 'warning';
            $data['summary'] = 'Pacing "Yo-Yo"';
            $data['points'][] = ['status' => 'warning', 'text' => 'Votre charge varie énormément, alternant entre des semaines très lourdes et très légères. Ce "choc" de charge constant est risqué.'];
            $data['recommendation'] = 'Visez une progression plus linéaire et plus douce. Une augmentation progressive est souvent plus efficace et plus sûre sur le long terme.';
        } else {
            $data['status'] = 'optimal';
            $data['summary'] = 'Pacing maîtrisé';
            $data['points'][] = ['status' => 'optimal', 'text' => 'La variation de votre charge est bien contrôlée. Cela favorise une adaptation solide et durable.'];
        }

        return $data;
    }

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

    private function getCalculatedMetricHistory(Athlete $athlete, CalculatedMetricType $metric, int $days, Carbon $endDate, ?Collection $allMetrics = null): Collection
    {
        $history = collect();
        $startDate = $endDate->copy()->subDays($days - 1);

        if ($allMetrics === null) {
            $metricsForPeriod = $athlete->metrics()->whereBetween('date', [$startDate, $endDate])->get()->groupBy(fn ($m) => $m->date->toDateString());
        } else {
            $metricsForPeriod = $allMetrics->whereBetween('date', [$startDate, $endDate])->groupBy(fn ($m) => $m->date->toDateString());
        }

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateString = $date->toDateString();
            $dailyMetrics = $metricsForPeriod->get($dateString, collect());
            $value = null;

            if ($metric === CalculatedMetricType::SBM) {
                $value = $this->calculationService->calculateSbmForCollection($dailyMetrics);
            } elseif ($metric === CalculatedMetricType::CIH) {
                $weekStartDate = $date->copy()->subDays(6);
                $weekMetrics = $allMetrics ?
                    $allMetrics->whereBetween('date', [$weekStartDate, $date]) :
                    $athlete->metrics()->whereBetween('date', [$weekStartDate, $date])->get();

                $value = $weekMetrics->where('metric_type', MetricType::POST_SESSION_SESSION_LOAD)->sum('value');
            }

            if ($value !== null) {
                $history->push((object) ['date' => $dateString, 'value' => $value]);
            }
        }

        return $history;
    }

    /**
     * Returns a glossary of technical terms used in the reports.
     */
    protected function getGlossary(): array
    {
        return [
            'ACWR'               => 'Acute:Chronic Workload Ratio. C\'est un indicateur qui compare votre charge d\'entraînement récente (aiguë) à votre charge habituelle (chronique) sur les 4 dernières semaines. Il aide à évaluer le risque de blessure lié à une augmentation trop rapide de la charge.',
            'SBM'                => 'Subjective Well-being Module. C\'est un score global de votre état de forme et de bien-être, basé sur vos ressentis matinaux (sommeil, fatigue, humeur, stress, douleurs). Un SBM élevé indique une bonne récupération.',
            'CIH'                => 'Charge d\'entraînement Interne Hebdomadaire. C\'est la somme de la charge ressentie pour toutes vos séances d\'entraînement sur une semaine. Elle reflète l\'effort total que votre corps a fourni.',
            'CPH'                => 'Charge d\'entraînement Planifiée Hebdomadaire. C\'est la charge d\'entraînement que votre entraîneur avait prévue pour vous sur une semaine. Comparer CIH et CPH permet de voir si vous avez suivi le plan.',
            'Damping'            => 'Amortissement psychologique. C\'est un état où votre moral est très bon, mais votre corps montre des signes de fatigue importants (par exemple, une VFC basse). C\'est un signal d\'alerte précoce de surentraînement.',
            'VFC'                => 'Variabilité de la Fréquence Cardiaque. C\'est une mesure de la variation du temps entre chaque battement de cœur. Une VFC élevée est généralement un signe de bonne récupération et d\'un système nerveux équilibré, tandis qu\'une VFC basse peut indiquer du stress ou de la fatigue.',
            'RPE'                => 'Rating of Perceived Exertion. C\'est une échelle de 1 à 10 pour évaluer subjectivement l\'intensité de votre effort pendant une séance d\'entraînement. 1 étant très facile et 10 étant un effort maximal.',
            'Hotspot de douleur' => 'Zone du corps où la douleur est la plus fréquente ou la plus intense sur une période donnée. Identifier ces hotspots permet de cibler les problèmes persistants.',
            'Pacing'             => 'Stratégie de gestion de la régularité de votre charge d\'entraînement. Un bon pacing signifie une progression linéaire et contrôlée, évitant les "chocs" de charge qui peuvent augmenter le risque de blessure.',
        ];
    }
}
