<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Athlete;

class Athlete3ScenariosSeeder extends Seeder
{
    /**
     * Run the database seeds for Athlete ID 3's scenarios.
     */
    public function run(): void
    {
        $athlete = Athlete::find(3);

        if (!$athlete) {
            $this->command->error("L'athlète ID 3 n'a pas été trouvé. Exécutez BaseDataSeeder d'abord.");
            return;
        }

        $this->command->info("Début du seeding des scénarios pour Athlète ID 3 (ID: {$athlete->id})");
        $this->command->newLine();

        $scenarioFactory = new MetricScenarioFactory($this->command);

        // Choisissez UN scénario à exécuter pour l'athlète 3 à la fois
        // Ces scénarios sont principalement axés sur les tendances générales
        // $scenarioFactory->seedFatigueDuringPeriodScenario($athlete->id); // Si l'athlète est féminine et on veut tester cette alerte
        // $scenarioFactory->seedLowPerformanceDuringPeriodScenario($athlete->id); // Si l'athlète est féminine
        // $scenarioFactory->seedGeneralFatigueTrendScenario($athlete->id);
        $scenarioFactory->seedHrvTrendScenario($athlete->id);
        // $scenarioFactory->seedSleepQualityTrendScenario($athlete->id);
        // $scenarioFactory->seedBodyWeightTrendScenario($athlete->id);
        // $scenarioFactory->seedNoAlertsScenario($athlete->id);

        // Scénarios d'alertes spécifiques (pour tester getAthleteAlerts)
        // $scenarioFactory->seedPersistentHighFatigueAlert($athlete->id);
        // $scenarioFactory->seedIncreasingFatigueTrendAlert($athlete->id);
        // $scenarioFactory->seedPersistentLowSleepQualityAlert($athlete->id);
        // $scenarioFactory->seedDecreasingSleepQualityTrendAlert($athlete->id);
        // $scenarioFactory->seedPersistentPainAlert($athlete->id);
        // $scenarioFactory->seedIncreasingPainTrendAlert($athlete->id);
        // $scenarioFactory->seedDecreasingHrvTrendAlert($athlete->id);
        // $scenarioFactory->seedDecreasingPerformanceFeelTrendAlert($athlete->id);
        // $scenarioFactory->seedSignificantWeightLossAlert($athlete->id);
    }
}