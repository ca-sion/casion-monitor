<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Athlete;
use App\Enums\MetricType;
use Illuminate\Support\Collection;

/**
 * NarrativeSummaryService : Génère un rapport narratif professionnel, lisible et orienté action.
 */
class NarrativeSummaryService
{
    // Déclaration de TOUS les services nécessaires
    protected MetricCalculationService $calculationService;

    protected MetricReadinessService $readinessService;

    protected MetricAlertsService $alertsService;

    protected MetricTrendsService $trendsService;

    protected MetricMenstrualService $menstrualService;

    public function __construct(
        MetricCalculationService $calculationService,
        MetricReadinessService $readinessService,
        MetricAlertsService $alertsService,
        MetricTrendsService $trendsService,
        MetricMenstrualService $menstrualService
    ) {
        $this->calculationService = $calculationService;
        $this->readinessService = $readinessService;
        $this->alertsService = $alertsService;
        $this->trendsService = $trendsService;
        $this->menstrualService = $menstrualService;
    }

    /**
     * Génère un résumé narratif de performance.
     *
     * @param  Athlete  $athlete  L'athlète concerné.
     * @param  Carbon  $endDate  La date de fin de la période d'analyse.
     * @return string Le texte narratif complet, formaté pour une lecture claire.
     */
    public function generateSummary(Athlete $athlete, Carbon $endDate): string
    {
        // --- A. COLLECTE / RECALCUL DES DONNÉES CLÉS ---

        // 1. Récupération des métriques (Large collection pour le cycle, quotidienne pour les alertes/readiness)
        $allMetrics = $athlete->metrics()->where('date', '<=', $endDate)->get();
        $dailyMetrics = $allMetrics->where('date', $endDate->toDateString());

        // 2. Readiness Score et détails
        $readinessData = $this->readinessService->calculateOverallReadinessScore($athlete, $allMetrics);
        $readinessScore = $readinessData['score'] ?? 50;
        $readinessDetails = $readinessData['details'] ?? [];

        // 3. Charge et Alertes
        $acwr = $this->calculationService->calculateAcwr($allMetrics, $endDate);
        $acwrThreshold = 1.3;
        $ratioCihCph = $this->calculationService->getLastRatioCihCph($athlete, $endDate);

        $alerts = $this->alertsService->checkAllAlerts($athlete, $dailyMetrics);
        $dangerAlerts = array_filter($alerts, fn ($a) => $a['type'] === 'danger');

        $moodDetail = collect($readinessDetails)->firstWhere('metric_short_label', 'Humeur');
        $hrvDetail = collect($readinessDetails)->firstWhere('metric_short_label', 'VFC');
        $isDamping = ($moodDetail['penalty'] ?? 10) < 5 && $readinessScore < 50 && ($hrvDetail['penalty'] ?? 0) >= 10;
        $isIncoherence = ($acwr > $acwrThreshold && $acwr !== null) && empty($dangerAlerts);

        // 4. Analyse Menstruelle
        $menstrualAnalysis = $athlete->is_female ?
            $this->menstrualService->deduceMenstrualCyclePhase($athlete, $allMetrics) : ['phase' => null, 'action' => null, 'status' => 'neutral'];

        // 5. Tendances et Corrélations
        $hrvMetrics = $allMetrics->where('metric_type', MetricType::MORNING_HRV->value)->where('date', '>=', $endDate->copy()->subDays(30));
        $sleepMetrics = $allMetrics->where('metric_type', MetricType::MORNING_SLEEP_QUALITY->value)->where('date', '>=', $endDate->copy()->subDays(30));
        $rpeMetrics = $allMetrics->where('metric_type', MetricType::POST_SESSION_SESSION_LOAD->value)->where('date', '>=', $endDate->copy()->subDays(30));
        $performanceMetrics = $allMetrics->where('metric_type', MetricType::POST_SESSION_PERFORMANCE_FEEL->value)->where('date', '>=', $endDate->copy()->subDays(30));

        $hrvSleepCorr = $this->trendsService->calculateCorrelationFromCollections($hrvMetrics, $sleepMetrics);
        $rpePerformanceCorr = $this->trendsService->calculateCorrelationFromCollections($rpeMetrics, $performanceMetrics);

        // --- LOGIQUE DE PROSE ---

        $narrative = '';

        // --- DÉTERMINATION DU STATUT & CONSEIL PRINCIPAL ---

        $finalAdvice = '';
        $readinessEmoji = '⚪';
        $pacingStatus = '';

        // Readiness Score
        if ($readinessScore >= 80) {
            $readinessStatus = "✨ excellent (score: {$readinessScore}/100)";
            $readinessDescription = "Votre corps est en **harmonie de récupération optimale** ; c'est le moment idéal pour supporter des charges maximales.";
            $readinessEmoji = '🟢';
        } elseif ($readinessScore >= 60) {
            $readinessStatus = "📈 bon (score: {$readinessScore}/100)";
            $readinessDescription = "La situation est **solide et robuste**. Vous pouvez maintenir le plan d'entraînement actuel avec progression modérée.";
            $readinessEmoji = '✅';
        } elseif ($readinessScore >= 40) {
            $readinessStatus = "⚠️ modéré (score: {$readinessScore}/100)";
            $readinessDescription = "Votre système est sous une **pression modérée**. Il est fortement conseillé d'**ajuster l'intensité ou le volume** de la prochaine séance.";
            $readinessEmoji = '🟡';
        } else {
            $readinessStatus = "🔴 critique (score: {$readinessScore}/100)";
            $readinessDescription = 'Votre corps signale une **défaillance de récupération**. La seule prescription est un **repos actif ou complet IMMEDIAT**.';
            $readinessEmoji = '🛑';
        }

        // ACWR
        if ($acwr !== null) {
            $acwrFormatted = number_format($acwr, 2);
            if ($acwr >= $acwrThreshold) {
                $pacingStatus = "Attention, une 🔥 **surcharge** est détectée (ACWR: {$acwrFormatted}), indiquant que votre charge récente est trop élevée. ";
                $pacingEmoji = '🚨';
            } elseif ($acwr <= 0.8) {
                $pacingStatus = "Une 🧊 **sous-charge** est présente (ACWR: {$acwrFormatted}), ce qui pourrait entraîner un déconditionnement. ";
                $pacingEmoji = '📉';
            } else {
                $pacingStatus = "Le ✅ **Pacing est optimal** (ACWR: {$acwrFormatted}), assurant une progression contrôlée et sécuritaire. ";
                $pacingEmoji = '🎯';
            }
        } else {
            $pacingStatus = "Le Pacing (ACWR) n'a pas pu être finalisé (données manquantes). ";
            $pacingEmoji = '❓';
        }

        // Feuille de Route Finale (Déjà calculée dans la logique originale, on la réutilise pour la prose)
        if ($readinessScore >= 80 && ($acwr === null || $acwr < $acwrThreshold)) {
            $finalStatus = 'voyant 🟢';
            $finalAdvice = 'Poussez sans réserve. Le corps est prêt à maximiser le gain de performance. C\'est un feu vert pour l\'intensité.';
        } elseif ($readinessScore >= 60 && ($acwr === null || $acwr < $acwrThreshold)) {
            $finalStatus = 'voyant 🟡';
            $finalAdvice = 'Maintenez le plan. La vigilance est de mise sur les facteurs de fatigue identifiés. Poursuite avec une charge modérée et contrôlée.';
        } elseif ($readinessScore < 40 || ($acwr !== null && $acwr >= $acwrThreshold) || $isDamping || ! empty($dangerAlerts)) {
            $finalStatus = 'voyant 🔴';
            $finalAdvice = '**Réduction de charge OBLIGATOIRE (minimum 20% ou repos complet)**. Le risque est réel et l\'organisme est en état de surcharge. Priorité à la récupération.';
        } else {
            $finalStatus = 'voyant ⚪️';
            $finalAdvice = 'La situation est stable, mais le potentiel de progression est limité. Le facteur limitant se trouve dans les détails de la récupération (sommeil, douleur, VFC). Ciblez les déficits.';
        }

        // --- DÉTAILS DES LEVIERS & TENDANCES ---

        $topPenalties = collect($readinessDetails)
            ->sortByDesc('penalty')
            ->filter(fn ($d) => $d['penalty'] > 0)
            ->take(2);

        $leverDetails = [];
        foreach ($topPenalties as $detail) {
            $metricLabel = $detail['metric_short_label'];
            if ($metricLabel === 'VFC') {
                $leverDetails[] = "la **Variabilité de la fréquence cardiaque (VFC)**, signe d'un stress nerveux majeur";
            } elseif ($metricLabel === 'Sommeil Qualité' || $metricLabel === 'Sommeil Durée') {
                $leverDetails[] = "**la qualité ou la durée de votre sommeil**, qui représente un obstacle majeur à l'adaptation";
            } elseif ($metricLabel === 'Fatigue') {
                $leverDetails[] = '**votre perception de la fatigue générale**, indiquant une accumulation maximale';
            } elseif ($metricLabel === 'Douleur') {
                $leverDetails[] = 'le **niveau de douleur** persistante (hotspot)';
            }
        }
        $leverSummary = count($leverDetails) > 0 ? "Le principal levier d'amélioration se concentre sur ".implode(' et ', $leverDetails).'.' : "Actuellement, aucun problème n'est relevé, l'équilibre est excellent. ✨";

        // TENDANCES
        $trendsSummary = 'Sur le long terme, ';
        $significantTrendFound = false;

        if ($hrvSleepCorr['correlation'] !== null && $hrvSleepCorr['correlation'] > 0.6) {
            $corr = number_format($hrvSleepCorr['correlation'], 2);
            $trendsSummary .= "votre **VFC et votre sommeil sont fortement liés (r={$corr}) 🔗**, confirmant que l'optimisation du sommeil est correcte. ";
            $significantTrendFound = true;
        }

        if ($rpePerformanceCorr['correlation'] !== null && $rpePerformanceCorr['correlation'] < -0.7) {
            $corr = number_format($rpePerformanceCorr['correlation'], 2);
            $trendsSummary .= "De plus, votre perception de l'effort (RPE) est très fiable (r={$corr}) 📐, ce qui est un bon indicateur de la charge. ";
            $significantTrendFound = true;
        }

        if (! $significantTrendFound) {
            $trendsSummary = "Le système n'a pas détecté de corrélation forte (> |0.6|) sur le dernier mois, ce qui rend l'analyse des leviers moins directe.";
        }

        // ALERTS CRITIQUES (DAMPING / INCOHERENCE / DANGER)
        $alertSummary = '';
        if (! empty($dangerAlerts)) {
            $alertMessages = array_map(fn ($a) => $a['message'], $dangerAlerts);
            $alertSummary = '🚨 Attention : '.implode('. ', $alertMessages).'.';
        } elseif ($isDamping) {
            $alertSummary = "Un 🛑 **Damping** (amortissement psychologique) est détecté : votre moral est bon, mais votre corps est épuisé. Votre perception est déconnectée de la réalité biologique. **Agissez sur la charge sans attendre l'effondrement moral.**";
        } elseif ($isIncoherence) {
            $alertSummary = 'Une ⚠️ **incohérence des données** est notée (Forte surcharge sans alerte danger). Attendez-vous à une chute brutale de la Readiness sous peu.';
        }

        // ANALYSE MENSTRUELLE
        $menstrualSummary = '';
        if ($athlete->is_female) {
            $phase = $menstrualAnalysis['phase'] ?? 'Non déterminée';
            $action = $menstrualAnalysis['action'] ?? 'N/A';

            if (($menstrualAnalysis['status'] ?? 'neutral') === 'critical') {
                $menstrualSummary = "⚠️ **Attention**, votre cycle est en déséquilibre (aménorrhée/oligoménorrhée). **Arrêt de l'entraînement intense et consultation médicale immédiate.**";
            } elseif ($phase === 'Phase Lutéale') {
                $menstrualSummary = "Actuellement en phase lutéale 🌕, il est conseillé de **{$action}** en privilégiant l'endurance, car la tolérance à l'intensité pure est réduite.";
            } elseif ($phase === 'Phase Folliculaire') {
                $menstrualSummary = "Actuellement en phase folliculaire 🌱, c'est la période idéale pour **{$action}** et pousser l'intensité si la Readiness globale le permet.";
            }
        }

        // --- CONSTRUCTION DES PARAGRAPHES ---
        $narrative .= "#### {$readinessEmoji} État actuel\n\n";

        // PARAGRAPHE 1 : RÉSUMÉ IMMÉDIAT (READINESS, PACING, ALERTES/LEVIERS)
        $p1 = "Au {$endDate->locale('fr_CH')->isoFormat('LL')}, votre état de récupération global est jugé **{$readinessStatus}**. $readinessDescription $pacingStatus";

        if (! empty($alertSummary)) {
            $p1 .= " $alertSummary";
        }

        $p1 .= " $leverSummary";

        $narrative .= $p1."\n\n";

        // PARAGRAPHE 2 : PERSPECTIVE LONG TERME & FEUILLE DE ROUTE
        $narrative .= "#### 🧭 Tendances & recommandations\n\n";

        $p2 = "$trendsSummary ";

        if (! empty($menstrualSummary)) {
            $p2 .= "Concernant le cycle, $menstrualSummary ";
        }

        $p2 .= "Pour la suite, **{$finalStatus}**. **{$finalAdvice}**.";

        $narrative .= $p2."\n\n";

        $narrative .= "\n*(Ce rapport est un outil d'aide à la décision. L'écoute de l'athlète et l'expertise de l'entraîneur restent les éléments les plus précieux.)*";

        return $narrative;
    }
}
