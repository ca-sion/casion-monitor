<?php

namespace Database\Seeders;

use Carbon\Carbon;
use App\Enums\MetricType;
// Garder si des méthodes self-contained sont nécessaires
use Illuminate\Support\Facades\DB;

/**
 * Classe utilitaire pour générer des scénarios de métriques pour les athlètes.
 */
class MetricScenarioFactory
{
    private $command; // Pour permettre l'affichage des informations

    public function __construct($command = null)
    {
        $this->command = $command;
    }

    private function info(string $message): void
    {
        if ($this->command) {
            $this->command->info($message);
        }
    }

    private function warn(string $message): void
    {
        if ($this->command) {
            $this->command->warn($message);
        }
    }

    private function newLine(): void
    {
        if ($this->command) {
            $this->command->newLine();
        }
    }

    /**
     * Insère une métrique pour un type donné.
     */
    private function insertMetric(int $athleteId, MetricType $metricType, int $daysAgo, float $value): void
    {
        DB::table('metrics')->insert([
            'athlete_id'  => $athleteId,
            'date'        => Carbon::now()->subDays($daysAgo)->startOfDay(),
            'metric_type' => $metricType->value,
            'value'       => $value,
            'unit'        => $metricType->getUnit(),
            'time'        => null, 'note' => null, 'metadata' => null,
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ]);
    }

    /**
     * Nettoie les métriques spécifiques pour un athlète.
     */
    private function cleanMetrics(int $athleteId, array $metricTypes): void
    {
        DB::table('metrics')
            ->where('athlete_id', $athleteId)
            ->whereIn('metric_type', array_map(fn ($type) => $type->value, $metricTypes))
            ->delete();
        $this->info("  - Nettoyage des métriques pour l'athlète ID : {$athleteId} pour les types spécifiés.");
    }

    // --- Scénarios de Cycle Menstruel (anciennement dans MetricAlertsSeeder) ---

    public function seedAmenorrheaScenario(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_FIRST_DAY_PERIOD]);
        $this->info('  - Scénario: Aménorrhée pour Athlète ID: '.$athleteId);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 124, 1.0);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 96, 1.0); // Dernière J1
        $this->info('    -> Attendu: Phase Aménorrhée, Alerte de Danger.');
        $this->newLine();
    }

    public function seedOligomenorrheaLongScenario(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_FIRST_DAY_PERIOD]);
        $this->info('  - Scénario: Oligoménorrhée (cycles longs) pour Athlète ID: '.$athleteId);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 135, 1.0);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 85, 1.0);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 35, 1.0); // Dernier J1
        $this->info('    -> Attendu: Phase Oligoménorrhée, Alerte de Danger (moy. ~50 jours).');
        $this->newLine();
    }

    public function seedOligomenorrheaShortScenario(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_FIRST_DAY_PERIOD]);
        $this->info('  - Scénario: Oligoménorrhée (Cycles courts) pour Athlète ID: '.$athleteId);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 56, 1.0);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 38, 1.0);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 20, 1.0); // Dernière J1
        $this->info('    -> Attendu: Phase Oligoménorrhée, Alerte de Danger (moy. 18 jours).');
        $this->newLine();
    }

    public function seedPotentialDelayScenario(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_FIRST_DAY_PERIOD]);
        $this->info('  - Scénario: Potentiel Retard (Nécessite modification de getAthleteAlerts) pour Athlète ID: '.$athleteId);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 92, 1.0);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 64, 1.0);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 34, 1.0); // Dernière J1
        $this->info("    -> Attendu: Phase Potentiel retard ou cycle long, Alerte d'Avertissement (moy. ~29 jours, 34 jours passés).");
        $this->newLine();
    }

    public function seedNormalCycleScenario(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_FIRST_DAY_PERIOD]);
        $this->info('  - Scénario: Cycle Normal pour Athlète ID: '.$athleteId);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 150, 1.0);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 122, 1.0);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 94, 1.0); // Dernière J1
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 66, 1.0);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 38, 1.0);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 10, 1.0); // Dernière J1
        $this->info('    -> Attendu: Phase Lutéale/Menstruelle, Alerte de Succès (moy. ~28.5 jours, 30 jours passés).');
        $this->newLine();
    }

    // --- Scénarios de Tendance (anciennement dans MetricTrendAlertsSeeder) ---

    public function seedFatigueDuringPeriodScenario(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_FIRST_DAY_PERIOD, MetricType::MORNING_GENERAL_FATIGUE]);
        $this->info('  - Scénario: Fatigue Élevée en phase Menstruelle pour Athlète ID: '.$athleteId);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 31, 1.0); // Ancien J1 pour calculer la moyenne
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 3, 1.0); // J1 actuel pour être en phase menstruelle
        $this->insertMetric($athleteId, MetricType::MORNING_GENERAL_FATIGUE, 0, 7.0);
        $this->info('    -> Attendu: Alerte INFO pour fatigue élevée durant la phase menstruelle.');
        $this->newLine();
    }

    public function seedLowPerformanceDuringPeriodScenario(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_FIRST_DAY_PERIOD, MetricType::POST_SESSION_PERFORMANCE_FEEL]);
        $this->info('  - Scénario: Performance Faible en phase Menstruelle pour Athlète ID: '.$athleteId);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 31, 1.0); // Ancien J1 pour calculer la moyenne
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 3, 1.0); // J1 actuel pour être en phase menstruelle
        $this->insertMetric($athleteId, MetricType::POST_SESSION_PERFORMANCE_FEEL, 0, 4.0);
        $this->info('    -> Attendu: Alerte INFO pour performance ressentie faible durant la phase menstruelle.');
        $this->newLine();
    }

    public function seedGeneralFatigueTrendScenario(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_GENERAL_FATIGUE]);
        $this->info('  - Scénario: Tendance Fatigue Générale (Danger) pour Athlète ID: '.$athleteId);
        for ($i = 30; $i >= 8; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_GENERAL_FATIGUE, $i, rand(20, 40) / 10);
        }
        for ($i = 7; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_GENERAL_FATIGUE, $i, rand(60, 90) / 10);
        }
        $this->info('    -> Attendu: Alerte DANGER/WARNING pour tendance de fatigue générale (selon seuils configurés).');
        $this->newLine();
    }

    public function seedHrvTrendScenario(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_HRV]);
        $this->info('  - Scénario: Tendance VFC (Danger) pour Athlète ID: '.$athleteId);
        for ($i = 30; $i >= 8; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_HRV, $i, rand(600, 800) / 10);
        }
        for ($i = 7; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_HRV, $i, rand(200, 400) / 10);
        }
        $this->info('    -> Attendu: Alerte DANGER/WARNING pour tendance VFC (selon seuils configurés).');
        $this->newLine();
    }

    public function seedSleepQualityTrendScenario(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_SLEEP_QUALITY]);
        $this->info('  - Scénario: Tendance Qualité de Sommeil (Warning) pour Athlète ID: '.$athleteId);
        for ($i = 30; $i >= 8; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_SLEEP_QUALITY, $i, rand(7, 9));
        }
        for ($i = 7; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_SLEEP_QUALITY, $i, rand(3, 5));
        }
        $this->info('    -> Attendu: Alerte DANGER/WARNING pour tendance qualité de sommeil (selon seuils configurés).');
        $this->newLine();
    }

    public function seedBodyWeightTrendScenario(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_BODY_WEIGHT_KG]);
        $this->info('  - Scénario: Tendance Poids Corporel (Warning) pour Athlète ID: '.$athleteId);
        for ($i = 30; $i >= 8; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_BODY_WEIGHT_KG, $i, rand(6800, 7000) / 100);
        }
        for ($i = 7; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_BODY_WEIGHT_KG, $i, rand(6400, 6600) / 100);
        }
        $this->info('    -> Attendu: Alerte DANGER/WARNING pour tendance poids corporel (selon seuils configurés).');
        $this->newLine();
    }

    public function seedNoAlertsScenario(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [
            MetricType::MORNING_GENERAL_FATIGUE,
            MetricType::MORNING_HRV,
            MetricType::MORNING_SLEEP_QUALITY,
            MetricType::MORNING_BODY_WEIGHT_KG,
            MetricType::POST_SESSION_PERFORMANCE_FEEL,
            MetricType::MORNING_FIRST_DAY_PERIOD,
        ]);
        $this->info("  - Scénario: RAS / Pas d'alertes spécifiques pour Athlète ID: ".$athleteId);
        for ($i = 30; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_GENERAL_FATIGUE, $i, rand(20, 30) / 10);
            $this->insertMetric($athleteId, MetricType::MORNING_HRV, $i, rand(700, 750) / 10);
            $this->insertMetric($athleteId, MetricType::MORNING_SLEEP_QUALITY, $i, rand(7, 8));
            $this->insertMetric($athleteId, MetricType::MORNING_BODY_WEIGHT_KG, $i, rand(6900, 7000) / 100);
            $this->insertMetric($athleteId, MetricType::POST_SESSION_PERFORMANCE_FEEL, $i, rand(7, 9));
            if ($i % 28 === 0) {
                $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, $i, 1.0);
            }
        }
        $this->info("    -> Attendu: Alerte SUCCESS (Aucun signal d'alerte majeur détecté).");
        $this->newLine();
    }

    // Scénarios spécifiques pour les alertes de getAthleteAlerts

    // Scénario pour 'Fatigue générale très élevée persistante'
    public function seedPersistentHighFatigueAlert(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_GENERAL_FATIGUE]);
        $this->info('  - Scénario: Alerte de fatigue générale persistante pour Athlète ID: '.$athleteId);
        // Moyenne 30 jours >= 6, Moyenne 7 jours >= 7
        for ($i = 60; $i >= 31; $i--) { // Données plus anciennes pour la moyenne 30 jours
            $this->insertMetric($athleteId, MetricType::MORNING_GENERAL_FATIGUE, $i, rand(60, 70) / 10); // 6.0-7.0
        }
        for ($i = 30; $i >= 8; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_GENERAL_FATIGUE, $i, rand(60, 65) / 10); // 6.0-6.5
        }
        for ($i = 7; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_GENERAL_FATIGUE, $i, rand(70, 80) / 10); // 7.0-8.0
        }
        $this->info('    -> Attendu: Alerte WARNING "Fatigue générale très élevée persistante".');
        $this->newLine();
    }

    // Scénario pour 'Augmentation significative de la fatigue générale'
    public function seedIncreasingFatigueTrendAlert(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_GENERAL_FATIGUE]);
        $this->info('  - Scénario: Alerte d\'augmentation de la fatigue générale pour Athlète ID: '.$athleteId);
        // Diminution puis augmentation significative
        for ($i = 60; $i >= 15; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_GENERAL_FATIGUE, $i, rand(30, 40) / 10); // 3.0-4.0
        }
        for ($i = 14; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_GENERAL_FATIGUE, $i, rand(50, 70) / 10); // 5.0-7.0 pour simuler une augmentation
        }
        $this->info('    -> Attendu: Alerte WARNING "Augmentation significative de la fatigue générale".');
        $this->newLine();
    }

    // Scénario pour 'Qualité de sommeil très faible persistante'
    public function seedPersistentLowSleepQualityAlert(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_SLEEP_QUALITY]);
        $this->info('  - Scénario: Alerte de qualité de sommeil très faible persistante pour Athlète ID: '.$athleteId);
        // Moyenne 30 jours <= 5, Moyenne 7 jours <= 4
        for ($i = 60; $i >= 31; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_SLEEP_QUALITY, $i, rand(4, 5));
        }
        for ($i = 30; $i >= 8; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_SLEEP_QUALITY, $i, rand(4, 5));
        }
        for ($i = 7; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_SLEEP_QUALITY, $i, rand(3, 4));
        }
        $this->info('    -> Attendu: Alerte WARNING "Qualité de sommeil très faible persistante".');
        $this->newLine();
    }

    // Scénario pour 'Diminution significative de la qualité du sommeil'
    public function seedDecreasingSleepQualityTrendAlert(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_SLEEP_QUALITY]);
        $this->info('  - Scénario: Alerte de diminution de la qualité du sommeil pour Athlète ID: '.$athleteId);
        // Haute puis baisse significative
        for ($i = 60; $i >= 15; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_SLEEP_QUALITY, $i, rand(7, 9));
        }
        for ($i = 14; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_SLEEP_QUALITY, $i, rand(3, 5));
        }
        $this->info('    -> Attendu: Alerte WARNING "Diminution significative de la qualité du sommeil".');
        $this->newLine();
    }

    // Scénario pour 'Douleurs musculaires/articulaires persistantes'
    public function seedPersistentPainAlert(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_PAIN]);
        $this->info('  - Scénario: Alerte de douleurs persistantes pour Athlète ID: '.$athleteId);
        // Moyenne 7 jours >= 5
        for ($i = 30; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_PAIN, $i, rand(5, 7)); // 5-7
        }
        $this->info('    -> Attendu: Alerte WARNING "Douleurs musculaires/articulaires persistantes".');
        $this->newLine();
    }

    // Scénario pour 'Augmentation significative des douleurs'
    public function seedIncreasingPainTrendAlert(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_PAIN]);
        $this->info('  - Scénario: Alerte d\'augmentation des douleurs pour Athlète ID: '.$athleteId);
        // Basse puis augmentation significative
        for ($i = 60; $i >= 15; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_PAIN, $i, rand(1, 3));
        }
        for ($i = 14; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_PAIN, $i, rand(5, 8));
        }
        $this->info('    -> Attendu: Alerte WARNING "Augmentation significative des douleurs".');
        $this->newLine();
    }

    // Scénario pour 'Diminution significative de la VFC'
    public function seedDecreasingHrvTrendAlert(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_HRV]);
        $this->info('  - Scénario: Alerte de diminution de la VFC pour Athlète ID: '.$athleteId);
        // Haute puis baisse significative
        for ($i = 60; $i >= 15; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_HRV, $i, rand(700, 900) / 10);
        }
        for ($i = 14; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_HRV, $i, rand(300, 500) / 10);
        }
        $this->info('    -> Attendu: Alerte WARNING "Diminution significative de la VFC".');
        $this->newLine();
    }

    // Scénario pour 'Diminution significative du ressenti de performance en séance'
    public function seedDecreasingPerformanceFeelTrendAlert(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::POST_SESSION_PERFORMANCE_FEEL]);
        $this->info('  - Scénario: Alerte de diminution du ressenti de performance pour Athlète ID: '.$athleteId);
        // Haute puis baisse significative
        for ($i = 60; $i >= 15; $i--) {
            $this->insertMetric($athleteId, MetricType::POST_SESSION_PERFORMANCE_FEEL, $i, rand(7, 9));
        }
        for ($i = 14; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::POST_SESSION_PERFORMANCE_FEEL, $i, rand(3, 5));
        }
        $this->info('    -> Attendu: Alerte WARNING "Diminution significative du ressenti de performance en séance".');
        $this->newLine();
    }

    // Scénario pour 'Perte de poids significative'
    public function seedSignificantWeightLossAlert(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_BODY_WEIGHT_KG]);
        $this->info('  - Scénario: Alerte de perte de poids significative pour Athlète ID: '.$athleteId);
        // Stable puis perte de plus de 3%
        for ($i = 60; $i >= 15; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_BODY_WEIGHT_KG, $i, 70.0 + (rand(-100, 100) / 100)); // ~70kg
        }
        for ($i = 14; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_BODY_WEIGHT_KG, $i, 66.0 + (rand(-100, 100) / 100)); // ~66kg (perte de ~4kg sur 70kg est > 3%)
        }
        $this->info('    -> Attendu: Alerte WARNING "Perte de poids significative".');
        $this->newLine();
    }

    // Scénario pour 'Absence de données sur le premier jour des règles.'
    public function seedNoFirstDayPeriodDataAlert(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_FIRST_DAY_PERIOD]);
        $this->info('  - Scénario: Alerte "Aucune donnée récente sur le premier jour des règles" pour Athlète ID: '.$athleteId);
        // Aucune donnée J1 insérée.
        $this->info('    -> Attendu: Alerte INFO "Aucune donnée récente sur le premier jour des règles pour cette athlète. Un suivi est recommandé."');
        $this->newLine();
    }

    // Scénario pour 'Absence de règles prolongée (plus de 45 jours sans J1 et sans cycle moyen)'
    public function seedProlongedAbsenceOfPeriodAlert(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_FIRST_DAY_PERIOD]);
        $this->info('  - Scénario: Alerte "Absence de règles prolongée" pour Athlète ID: '.$athleteId);
        // Un seul J1 très ancien pour simuler l'absence de données de cycle moyen
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 50, 1.0); // Dernière J1 il y a 50 jours
        $this->info('    -> Attendu: Alerte DANGER "Absence de règles prolongée".');
        $this->newLine();
    }

    // Scénario pour 'Fatigue élevée pendant la phase menstruelle'
    public function seedFatigueDuringMenstrualPhaseInfo(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_FIRST_DAY_PERIOD, MetricType::MORNING_GENERAL_FATIGUE]);
        $this->info('  - Scénario: Info fatigue élevée pendant la phase menstruelle pour Athlète ID: '.$athleteId);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 28, 1.0); // J1 il y a 3 jours (phase menstruelle)
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 3, 1.0); // J1 il y a 3 jours (phase menstruelle)
        $this->insertMetric($athleteId, MetricType::MORNING_GENERAL_FATIGUE, 4, 7.0); // Fatigue du jour
        $this->insertMetric($athleteId, MetricType::MORNING_GENERAL_FATIGUE, 0, 7.0); // Fatigue du jour
        $this->info('    -> Attendu: Alerte INFO "Fatigue élevée pendant la phase menstruelle".');
        $this->newLine();
    }

    // Scénario pour 'Performance ressentie faible pendant la phase menstruelle'
    public function seedLowPerformanceDuringMenstrualPhaseInfo(int $athleteId): void
    {
        $this->cleanMetrics($athleteId, [MetricType::MORNING_FIRST_DAY_PERIOD, MetricType::POST_SESSION_PERFORMANCE_FEEL]);
        $this->info('  - Scénario: Info performance faible pendant la phase menstruelle pour Athlète ID: '.$athleteId);
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 28, 1.0); // J1 il y a 3 jours (phase menstruelle)
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 3, 1.0); // J1 il y a 3 jours (phase menstruelle)
        $this->insertMetric($athleteId, MetricType::POST_SESSION_PERFORMANCE_FEEL, 4, 4.0); // Performance du jour
        $this->insertMetric($athleteId, MetricType::POST_SESSION_PERFORMANCE_FEEL, 0, 4.0); // Performance du jour
        $this->info('    -> Attendu: Alerte INFO "Performance ressentie faible pendant la phase menstruelle".');
        $this->newLine();
    }
}
