<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Athlete;

class Athlete1ScenariosSeeder extends Seeder
{
    /**
     * Run the database seeds for Arthur's scenarios.
     */
    public function run(): void
    {
        $athlete = Athlete::find(1);

        if (!$athlete) {
            $this->command->error("L'athlète Arthur n'a pas été trouvé. Exécutez BaseDataSeeder d'abord.");
            return;
        }

        $this->command->info("Début du seeding des scénarios pour Arthur (ID: {$athlete->id})");
        $this->command->newLine();

        $scenarioFactory = new MetricScenarioFactory($this->command);

        // Décommentez le scénario que vous souhaitez tester pour Arthur
        // Note: Arthur est un homme, donc les scénarios liés au cycle menstruel ne s'appliquent pas.

        // Scénario : Tendance de Fatigue Générale (Danger/Warning)
        // $scenarioFactory->seedGeneralFatigueTrendScenario($athlete->id);

        // Scénario : Tendance VFC (Danger/Warning)
        // $scenarioFactory->seedHrvTrendScenario($athlete->id);

        // Scénario : Tendance Qualité de Sommeil (Danger/Warning)
        // $scenarioFactory->seedSleepQualityTrendScenario($athlete->id);

        // Scénario : Tendance Poids Corporel (Warning si forte variation)
        // $scenarioFactory->seedBodyWeightTrendScenario($athlete->id);

        // Scénario : RAS / Pas d'alertes spécifiques (Alerte Succès)
        $scenarioFactory->seedNoAlertsScenario($athlete->id);

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