<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Athlete;

class Athlete2ScenariosSeeder extends Seeder
{
    /**
     * Run the database seeds for Guenièvre's scenarios.
     */
    public function run(): void
    {
        $athlete = Athlete::find(2);

        if (!$athlete) {
            $this->command->error("L'athlète Guenièvre n'a pas été trouvée. Exécutez BaseDataSeeder d'abord.");
            return;
        }

        // Assurez-vous que l'athlète est bien une femme pour les tests du cycle
        if ($athlete->gender !== 'w') {
            $this->command->warn("L'athlète Guenièvre (ID {$athlete->id}) n'est pas féminine. Mise à jour de son genre à 'w'.");
            $athlete->update(['gender' => 'w']);
        }

        $this->command->info("Début du seeding des scénarios pour Guenièvre (ID: {$athlete->id})");
        $this->command->newLine();

        $scenarioFactory = new MetricScenarioFactory($this->command);

        // Décommentez le scénario que vous souhaitez tester pour Guenièvre

        // Scénarios de cycle menstruel
        // $scenarioFactory->seedAmenorrheaScenario($athlete->id);
        // $scenarioFactory->seedOligomenorrheaLongScenario($athlete->id);
        // $scenarioFactory->seedOligomenorrheaShortScenario($athlete->id);
        // $scenarioFactory->seedPotentialDelayScenario($athlete->id);
        // $scenarioFactory->seedNormalCycleScenario($athlete->id);

        // Scénarios de tendance générale (applicables aux femmes)
        // $scenarioFactory->seedGeneralFatigueTrendScenario($athlete->id);
        // $scenarioFactory->seedHrvTrendScenario($athlete->id);
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
        // $scenarioFactory->seedNoFirstDayPeriodDataAlert($athlete->id); // Si aucune donnée J1 n'est présente
        // $scenarioFactory->seedProlongedAbsenceOfPeriodAlert($athlete->id); // Si un seul J1 très ancien
        // $scenarioFactory->seedFatigueDuringMenstrualPhaseInfo($athlete->id);
        // $scenarioFactory->seedLowPerformanceDuringMenstrualPhaseInfo($athlete->id);

        // Exemple : un scénario complet
        $scenarioFactory->seedNormalCycleScenario($athlete->id);
        // $scenarioFactory->seedNoAlertsScenario($athlete->id);
    }
}