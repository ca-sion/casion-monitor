<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Athlete;
use App\Enums\MetricType; // Assurez-vous que MetricType contient POST_SESSION_SUBJECTIVE_FATIGUE
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Athlete1RealisticScenariosSeeder extends Seeder
{
    /**
     * Seed the database with realistic data for Athlete ID 1 over 8 months.
     */
    public function run(): void
    {
        $athlete = Athlete::find(1); // Athlète ID 1 (Arthur)

        if (!$athlete) {
            $this->command->error("L'athlète ID 1 n'a pas été trouvé. Exécutez BaseDataSeeder d'abord.");
            return;
        }

        $this->command->info("Début du seeding des scénarios réalistes pour Arthur (ID: {$athlete->id}) sur 8 mois.");
        $this->command->newLine();

        // Nettoyer toutes les métriques existantes pour cet athlète avant de semer de nouvelles données
        DB::table('metrics')->where('athlete_id', $athlete->id)->delete();
        $this->command->info("  - Nettoyage de toutes les métriques existantes pour l'athlète ID: {$athlete->id}.");
        $this->command->newLine();

        $startDate = Carbon::now()->subMonths(8)->startOfDay();
        $endDate = Carbon::now()->startOfDay();

        $currentDate = $startDate->copy();

        // Paramètres de base pour les métriques
        $baseFatigue = 3.5; // Échelle 1-10
        $baseHrv = 120.0;    // ms
        $baseSleepQuality = 7.5; // Échelle 1-10
        $baseBodyWeight = 75.0; // kg
        $baseRestingHr = 52.0; // bpm
        $basePerformanceFeel = 7.5; // Échelle 1-10
        $baseSubjectiveFatigue = 6.0; // Échelle 1-10
        $basePain = 1.0; // Échelle 1-10

        // Simuler des phases d'entraînement (intensité, récupération)
        $trainingPhases = [
            'high_intensity_1' => ['duration' => 60, 'fatigue_mod' => 1.8, 'hrv_mod' => -0.12, 'sleep_mod' => -0.7, 'subj_fatigue_mod' => 1.8, 'perf_mod' => -0.8, 'pain_prob' => 0.25],
            'recovery_1'       => ['duration' => 30, 'fatigue_mod' => -1.2, 'hrv_mod' => 0.18, 'sleep_mod' => 1.0, 'subj_fatigue_mod' => -1.2, 'perf_mod' => 1.0, 'pain_prob' => 0.08],
            'medium_intensity_1' => ['duration' => 45, 'fatigue_mod' => 1.0, 'hrv_mod' => -0.08, 'sleep_mod' => -0.5, 'subj_fatigue_mod' => 1.0, 'perf_mod' => -0.5, 'pain_prob' => 0.15],
            'peak_performance' => ['duration' => 30, 'fatigue_mod' => 0.3, 'hrv_mod' => 0.12, 'sleep_mod' => 0.7, 'subj_fatigue_mod' => 0.3, 'perf_mod' => 1.2, 'pain_prob' => 0.05],
            'off_season'       => ['duration' => 60, 'fatigue_mod' => -1.8, 'hrv_mod' => 0.22, 'sleep_mod' => 1.2, 'subj_fatigue_mod' => -1.8, 'perf_mod' => 0.7, 'pain_prob' => 0.03],
            'high_intensity_2' => ['duration' => 50, 'fatigue_mod' => 1.5, 'hrv_mod' => -0.10, 'sleep_mod' => -0.6, 'subj_fatigue_mod' => 1.5, 'perf_mod' => -0.6, 'pain_prob' => 0.20],
            'recovery_2'       => ['duration' => 20, 'fatigue_mod' => -1.0, 'hrv_mod' => 0.15, 'sleep_mod' => 0.8, 'subj_fatigue_mod' => -1.0, 'perf_mod' => 0.8, 'pain_prob' => 0.06],
            'competition_prep' => ['duration' => 40, 'fatigue_mod' => 0.7, 'hrv_mod' => 0.07, 'sleep_mod' => 0.3, 'subj_fatigue_mod' => 0.7, 'perf_mod' => 0.7, 'pain_prob' => 0.10],
            'taper'            => ['duration' => 15, 'fatigue_mod' => -0.7, 'hrv_mod' => 0.12, 'sleep_mod' => 0.7, 'subj_fatigue_mod' => -0.7, 'perf_mod' => 1.0, 'pain_prob' => 0.04],
            'post_competition' => ['duration' => 10, 'fatigue_mod' => 0.7, 'hrv_mod' => -0.07, 'sleep_mod' => -0.3, 'subj_fatigue_mod' => 0.7, 'perf_mod' => -0.3, 'pain_prob' => 0.12],
        ];

        $totalDays = $startDate->diffInDays($endDate);
        $daysProcessed = 0;
        $monthCounter = 0; // Pour le poids mensuel

        // Stocker le dernier poids enregistré pour une variation subtile
        $currentBodyWeight = $baseBodyWeight;

        foreach ($trainingPhases as $phaseName => $phaseParams) {
            $phaseDuration = $phaseParams['duration'];
            if ($daysProcessed + $phaseDuration > $totalDays) {
                $phaseDuration = $totalDays - $daysProcessed;
            }

            for ($i = 0; $i < $phaseDuration; $i++) {
                if ($currentDate->gt($endDate)) {
                    break 2; // Sortir des deux boucles si on dépasse la date de fin
                }

                $daysAgo = $endDate->diffInDays($currentDate);

                // Insertion du poids mensuel (une fois par mois)
                // L'idée est de l'enregistrer au début de chaque mois simulé
                // ou tous les ~30 jours de données générées.
                if ($daysProcessed % 30 == 0 || $daysProcessed == 0) {
                     // Ajustement mensuel du poids (petite variation sur le poids actuel)
                    $currentBodyWeight = $currentBodyWeight + (rand(-30, 30) / 100); // +/- 0.3kg
                    $currentBodyWeight = max(70.0, min(80.0, $currentBodyWeight)); // Maintenir dans une plage réaliste
                    $this->insertMetric($athlete->id, MetricType::MORNING_BODY_WEIGHT_KG, $daysAgo, $currentBodyWeight);
                    $monthCounter++;
                }

                // Fatigue Générale (MORNING_GENERAL_FATIGUE)
                $fatigue = max(1.0, min(10.0, $baseFatigue + $phaseParams['fatigue_mod'] + (rand(-10, 10) / 100)));
                $this->insertMetric($athlete->id, MetricType::MORNING_GENERAL_FATIGUE, $daysAgo, $fatigue);

                // VFC (MORNING_HRV)
                $hrv = max(80.0, min(160.0, $baseHrv + ($baseHrv * $phaseParams['hrv_mod']) + (rand(-50, 50) / 100)));
                $this->insertMetric($athlete->id, MetricType::MORNING_HRV, $daysAgo, $hrv);

                // Qualité de Sommeil (MORNING_SLEEP_QUALITY)
                $sleepQuality = max(1.0, min(10.0, $baseSleepQuality + $phaseParams['sleep_mod'] + (rand(-5, 5) / 10)));
                $this->insertMetric($athlete->id, MetricType::MORNING_SLEEP_QUALITY, $daysAgo, $sleepQuality);

                // Fréquence Cardiaque au Repos (MORNING_RESTING_HEART_RATE)
                // $restingHr = max(40.0, min(70.0, $baseRestingHr - ($baseRestingHr * $phaseParams['hrv_mod'] * 0.5) + (rand(-100, 100) / 100))); // Inverse de HRV
                // $this->insertMetric($athlete->id, MetricType::MORNING_RESTING_HEART_RATE, $daysAgo, $restingHr);

                // Ressenti de Performance Post-Séance (POST_SESSION_PERFORMANCE_FEEL)
                $performanceFeel = max(1.0, min(10.0, $basePerformanceFeel + $phaseParams['perf_mod'] + (rand(-5, 5) / 10)));
                $this->insertMetric($athlete->id, MetricType::POST_SESSION_PERFORMANCE_FEEL, $daysAgo, $performanceFeel);

                // Fatigue Subjective Post-Séance (POST_SESSION_SUBJECTIVE_FATIGUE)
                $subjectiveFatigue = max(1.0, min(10.0, $baseSubjectiveFatigue + $phaseParams['subj_fatigue_mod'] + (rand(-5, 5) / 10)));
                $this->insertMetric($athlete->id, MetricType::POST_SESSION_SUBJECTIVE_FATIGUE, $daysAgo, $subjectiveFatigue);

                // Douleur (MORNING_PAIN) - sporadiquement et généralement basse
                if (mt_rand() / mt_getrandmax() < $phaseParams['pain_prob']) {
                    $pain = rand(2, 6); // Douleur légère à modérée
                    $this->insertMetric($athlete->id, MetricType::MORNING_PAIN, $daysAgo, $pain);
                } else {
                    $this->insertMetric($athlete->id, MetricType::MORNING_PAIN, $daysAgo, $basePain); // Pas de douleur significative
                }

                $currentDate->addDay();
                $daysProcessed++;
            }
        }

        $this->command->info("  - Données réalistes générées pour Arthur sur {$daysProcessed} jours.");
        $this->command->newLine();
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
            'unit'        => $metricType->getUnit(), // Assurez-vous que votre Enum MetricType a une méthode getUnit()
            'time'        => null, 'note' => null, 'metadata' => null,
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ]);
    }
}