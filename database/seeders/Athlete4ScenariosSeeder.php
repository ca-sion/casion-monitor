<?php

namespace Database\Seeders;

use App\Models\Athlete;
use Illuminate\Database\Seeder;

class Athlete4ScenariosSeeder extends Seeder
{
    /**
     * Run the database seeds for Athlete ID 4's scenarios.
     */
    public function run(): void
    {
        $athlete = Athlete::find(4);

        if (! $athlete) {
            $this->command->error("L'athlète ID 4 n'a pas été trouvé. Exécutez BaseDataSeeder d'abord.");

            return;
        }

        // Assurez-vous que l'athlète 4 est bien une femme pour les tests du cycle
        if ($athlete->gender->value !== 'w') {
            $this->command->warn("L'athlète ID 4 n'est pas féminine. Mise à jour de son genre à 'w'.");
            $athlete->update(['gender' => 'w']);
        }

        $this->command->info("Début du seeding des scénarios pour Athlète ID 4 (ID: {$athlete->id})");
        $this->command->newLine();

        $scenarioFactory = new MetricScenarioFactory($this->command);

        // Choisissez UN scénario à exécuter pour l'athlète 4 à la fois
        // Ces scénarios sont principalement axés sur le cycle menstruel
        // $scenarioFactory->seedAmenorrheaScenario($athlete->id);
        // $scenarioFactory->seedOligomenorrheaLongScenario($athlete->id);
        // $scenarioFactory->seedOligomenorrheaShortScenario($athlete->id);
        $scenarioFactory->seedPotentialDelayScenario($athlete->id);
        // $scenarioFactory->seedNormalCycleScenario($athlete->id);

        // Scénarios d'alertes spécifiques (pour tester getAthleteAlerts)
        // $scenarioFactory->seedNoFirstDayPeriodDataAlert($athlete->id); // Si aucune donnée J1 n'est présente
        // $scenarioFactory->seedProlongedAbsenceOfPeriodAlert($athlete->id); // Si un seul J1 très ancien
        // $scenarioFactory->seedFatigueDuringMenstrualPhaseInfo($athlete->id);
        // $scenarioFactory->seedLowPerformanceDuringMenstrualPhaseInfo($athlete->id);
        // $scenarioFactory->seedFatigueDuringPeriodScenario($athlete->id);
        // $scenarioFactory->seedLowPerformanceDuringPeriodScenario($athlete->id);
    }
}
