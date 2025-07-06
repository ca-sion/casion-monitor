<?php

namespace Database\Seeders;

use Carbon\Carbon;
use App\Models\Athlete;
use App\Enums\MetricType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MetricTrendAlertsSeeder extends Seeder
{
    /**
     * Exécute les graines de la base de données.
     */
    public function run(): void
    {
        // Récupérer l'athlète avec l'ID 3 (ou en créer un si il n'existe pas)
        $athlete = Athlete::find(3);
        if (! $athlete) {
            // Créer un athlète avec l'ID 3 pour les tests
            $this->command->info("Création de l'athlète ID 3...");
            $athlete = Athlete::create([
                'id'         => 3,
                'first_name' => 'Athlète',
                'last_name'  => 'De Test 2',
                'gender'     => 'w', // Peut être 'm' ou 'w' car ces alertes ne dépendent pas du cycle
                'email'      => 'athlete3@example.com',
            ]);
        }

        // Nettoyer les données existantes de l'athlète pour ces métriques spécifiques
        DB::table('metrics')
            ->where('athlete_id', $athlete->id)
            ->whereIn('metric_type', [
                MetricType::MORNING_GENERAL_FATIGUE->value,
                MetricType::MORNING_HRV->value,
                MetricType::MORNING_SLEEP_QUALITY->value,
                MetricType::MORNING_BODY_WEIGHT_KG->value,
                MetricType::POST_SESSION_PERFORMANCE_FEEL->value,
                MetricType::MORNING_FIRST_DAY_PERIOD->value, // Pour les tests de phase menstruelle
            ])
            ->delete();

        $this->command->info("Début du seeding des métriques de tendance pour l'athlète ID : {$athlete->id}");
        $this->command->newLine();

        // **Pour exécuter un scénario spécifique, commentez les autres appels**
        // et décommentez celui que vous voulez tester.
        // Puis lancez le seeder avec : php artisan db:seed --class=MetricTrendAlertsSeeder

        // Scénario A : Fatigue Élevée en phase Menstruelle (Alerte Info)
        // La dernière J1 sera à (Carbon::now() - 3 jours) pour être en phase Menstruelle.
        // La fatigue du jour sera élevée (e.g., 7 ou 8).
        // $this->seedFatigueDuringPeriodScenario($athlete->id);

        // Scénario B : Performance Faible en phase Menstruelle (Alerte Info)
        // La dernière J1 sera à (Carbon::now() - 3 jours) pour être en phase Menstruelle.
        // La performance du jour sera basse (e.g., 4 ou 3).
        // $this->seedLowPerformanceDuringPeriodScenario($athlete->id);

        // Scénario C : Tendance de Fatigue Générale (Danger/Warning)
        // La moyenne 7 jours sera significativement plus élevée que la moyenne 30 jours.
        // $this->seedGeneralFatigueTrendScenario($athlete->id);

        // Scénario D : Tendance VFC (Danger/Warning)
        // La moyenne 7 jours sera significativement plus basse que la moyenne 30 jours.
        $this->seedHrvTrendScenario($athlete->id);

        // Scénario E : Tendance Qualité de Sommeil (Danger/Warning)
        // La moyenne 7 jours sera significativement plus basse que la moyenne 30 jours.
        // $this->seedSleepQualityTrendScenario($athlete->id);

        // Scénario F : Tendance Poids Corporel (Warning si forte variation)
        // La moyenne 7 jours sera significativement différente de la moyenne 30 jours.
        // $this->seedBodyWeightTrendScenario($athlete->id);

        // Scénario G : RAS / Pas d'alertes spécifiques (Alerte Succès)
        // Toutes les métriques seront dans les plages normales avec des tendances stables.
        // $this->seedNoAlertsScenario($athlete->id);
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

    private function seedFatigueDuringPeriodScenario(int $athleteId): void
    {
        $this->command->info('  - Scénario: Fatigue Élevée en phase Menstruelle');
        // Assurer une phase menstruelle (J1 il y a 3 jours)
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 3, 1.0);
        // Fatigue élevée le jour actuel (seuil >= 7 pour alerte INFO)
        $this->insertMetric($athleteId, MetricType::MORNING_GENERAL_FATIGUE, 0, 7.0); // Aujourd'hui

        $this->command->info('    -> Attendu: Alerte INFO pour fatigue élevée durant la phase menstruelle.');
        $this->command->newLine();
    }

    private function seedLowPerformanceDuringPeriodScenario(int $athleteId): void
    {
        $this->command->info('  - Scénario: Performance Faible en phase Menstruelle');
        // Assurer une phase menstruelle (J1 il y a 3 jours)
        $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, 3, 1.0);
        // Performance ressentie faible le jour actuel (seuil <= 4 pour alerte INFO)
        $this->insertMetric($athleteId, MetricType::POST_SESSION_PERFORMANCE_FEEL, 0, 4.0); // Aujourd'hui

        $this->command->info('    -> Attendu: Alerte INFO pour performance ressentie faible durant la phase menstruelle.');
        $this->command->newLine();
    }

    private function seedGeneralFatigueTrendScenario(int $athleteId): void
    {
        $this->command->info('  - Scénario: Tendance Fatigue Générale (Danger)');
        // Données pour avoir une moyenne 30 jours (stable et basse)
        for ($i = 30; $i >= 8; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_GENERAL_FATIGUE, $i, rand(20, 40) / 10); // 2.0 à 4.0
        }
        // Données pour avoir une moyenne 7 jours (significativement plus haute)
        for ($i = 7; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_GENERAL_FATIGUE, $i, rand(60, 90) / 10); // 6.0 à 9.0
        }
        $this->command->info('    -> Attendu: Alerte DANGER/WARNING pour tendance de fatigue générale (selon seuils configurés).');
        $this->command->newLine();
    }

    private function seedHrvTrendScenario(int $athleteId): void
    {
        $this->command->info('  - Scénario: Tendance VFC (Danger)');
        // Données pour avoir une moyenne 30 jours (stable et haute)
        for ($i = 30; $i >= 8; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_HRV, $i, rand(600, 800) / 10); // 60-80 ms
        }
        // Données pour avoir une moyenne 7 jours (significativement plus basse)
        for ($i = 7; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_HRV, $i, rand(200, 400) / 10); // 20-40 ms
        }
        $this->command->info('    -> Attendu: Alerte DANGER/WARNING pour tendance VFC (selon seuils configurés).');
        $this->command->newLine();
    }

    private function seedSleepQualityTrendScenario(int $athleteId): void
    {
        $this->command->info('  - Scénario: Tendance Qualité de Sommeil (Warning)');
        // Données pour avoir une moyenne 30 jours (stable et bonne)
        for ($i = 30; $i >= 8; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_SLEEP_QUALITY, $i, rand(7, 9)); // 7-9
        }
        // Données pour avoir une moyenne 7 jours (significativement plus basse)
        for ($i = 7; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_SLEEP_QUALITY, $i, rand(3, 5)); // 3-5
        }
        $this->command->info('    -> Attendu: Alerte DANGER/WARNING pour tendance qualité de sommeil (selon seuils configurés).');
        $this->command->newLine();
    }

    private function seedBodyWeightTrendScenario(int $athleteId): void
    {
        $this->command->info('  - Scénario: Tendance Poids Corporel (Warning)');
        // Données pour avoir une moyenne 30 jours (stable)
        for ($i = 30; $i >= 8; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_BODY_WEIGHT_KG, $i, rand(6800, 7000) / 100); // 68.00-70.00 kg
        }
        // Données pour avoir une moyenne 7 jours (variation significative)
        for ($i = 7; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_BODY_WEIGHT_KG, $i, rand(6400, 6600) / 100); // 64.00-66.00 kg (perte de poids)
        }
        $this->command->info('    -> Attendu: Alerte DANGER/WARNING pour tendance poids corporel (selon seuils configurés).');
        $this->command->newLine();
    }

    private function seedNoAlertsScenario(int $athleteId): void
    {
        $this->command->info("  - Scénario: RAS / Pas d'alertes spécifiques");
        // Données stables pour toutes les métriques
        for ($i = 30; $i >= 0; $i--) {
            $this->insertMetric($athleteId, MetricType::MORNING_GENERAL_FATIGUE, $i, rand(20, 30) / 10);
            $this->insertMetric($athleteId, MetricType::MORNING_HRV, $i, rand(700, 750) / 10);
            $this->insertMetric($athleteId, MetricType::MORNING_SLEEP_QUALITY, $i, rand(7, 8));
            $this->insertMetric($athleteId, MetricType::MORNING_BODY_WEIGHT_KG, $i, rand(6900, 7000) / 100);
            $this->insertMetric($athleteId, MetricType::POST_SESSION_PERFORMANCE_FEEL, $i, rand(7, 9));
            // Pour s'assurer qu'il n'y a pas d'alerte menstruelle non plus
            if ($i % 28 === 0) { // Simule un J1 tous les 28 jours
                $this->insertMetric($athleteId, MetricType::MORNING_FIRST_DAY_PERIOD, $i, 1.0);
            }
        }
        $this->command->info("    -> Attendu: Alerte SUCCESS (Aucun signal d'alerte majeur détecté).");
        $this->command->newLine();
    }
}
