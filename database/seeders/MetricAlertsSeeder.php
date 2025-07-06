<?php

namespace Database\Seeders;

use Carbon\Carbon;
use App\Models\Athlete;
use App\Enums\MetricType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MetricAlertsSeeder extends Seeder
{
    /**
     * Exécute les graines de la base de données.
     */
    public function run(): void
    {
        // Récupérer l'athlète avec l'ID 4 (ou en créer un si elle n'existe pas)
        $athlete = Athlete::find(4);
        if (! $athlete) {
            // Créer une athlète féminine avec l'ID 4 pour les tests
            $this->command->info("Création de l'athlète ID 4...");
            $athlete = Athlete::create([
                'id'         => 4,
                'first_name' => 'Athlète féminine',
                'last_name'  => 'De Test ',
                'gender'     => 'w', // Doit être 'w' pour la logique du cycle menstruel
                'email'      => 'athlete2@example.com',
            ]);
        } elseif ($athlete->gender !== 'w') {
            // Assurez-vous que l'athlète 4 est bien une femme pour les tests du cycle
            $this->command->warn("L'athlète ID 4 n'est pas féminine. Mise à jour de son genre à 'w'.");
            $athlete->update(['gender' => 'w']);
        }

        // Nettoyer les données existantes de 'morning_first_day_period' pour cet athlète
        DB::table('metrics')
            ->where('athlete_id', $athlete->id)
            ->where('metric_type', MetricType::MORNING_FIRST_DAY_PERIOD->value)
            ->delete();

        $this->command->info("Début du seeding des métriques de cycle menstruel pour l'athlète ID : {$athlete->id}");
        $this->command->newLine();

        // Pour exécuter un scénario spécifique, commentez les autres appels et décommentez celui que vous voulez tester.
        // Puis lancez le seeder (voir instructions ci-dessous).

        // Scénario 1 : Aménorrhée (Alerte de Danger)
        // La dernière J1 sera à (Carbon::now() - 96 jours). Le cycle moyen est de 28 jours.
        // $this->seedAmenorrheaScenario($athlete->id);

        // Scénario 2 : Oligoménorrhée (Alerte de Danger - Cycles longs)
        // La dernière J1 sera à (Carbon::now() - 16 jours). Le cycle moyen est de 40 jours.
        // $this->seedOligomenorrheaLongScenario($athlete->id);

        // Scénario 3 : Oligoménorrhée (Alerte de Danger - Cycles courts)
        // La dernière J1 sera à (Carbon::now() - 20 jours). Le cycle moyen est de 18 jours.
        // $this->seedOligomenorrheaShortScenario($athlete->id);

        // Scénario 4 : Potentiel Retard (Alerte d'Avertissement)
        // La dernière J1 sera à (Carbon::now() - 34 jours). Le cycle moyen est d'environ 29 jours.
        $this->seedPotentialDelayScenario($athlete->id);

        // Scénario 5 : Cycle Normal (Alerte de Succès / Pas d'alerte de cycle spécifique)
        // La dernière J1 sera à (Carbon::now() - 30 jours). Le cycle moyen est d'environ 28.5 jours.
        // $this->seedNormalCycleScenario($athlete->id);
    }

    /**
     * Insère une métrique pour le premier jour des règles.
     *
     * @param  int  $athleteId  L'ID de l'athlète.
     * @param  int  $daysAgo  Le nombre de jours avant aujourd'hui.
     */
    private function insertMetric(int $athleteId, int $daysAgo): void
    {
        DB::table('metrics')->insert([
            'athlete_id'  => $athleteId,
            'date'        => Carbon::now()->subDays($daysAgo)->startOfDay(), // Date relative à 'now'
            'metric_type' => MetricType::MORNING_FIRST_DAY_PERIOD->value,
            'value'       => 1.0,
            'unit'        => null, 'time' => null, 'note' => null, 'metadata' => null,
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ]);
    }

    private function seedAmenorrheaScenario(int $athleteId): void
    {
        $this->command->info('  - Scenario: Aménorrhée');
        // Dates: J1 - 124 jours (2025-03-04), J1 - 96 jours (2025-04-01)
        $this->insertMetric($athleteId, 124);
        $this->insertMetric($athleteId, 96); // Dernière J1
        $this->command->info('    -> Attendu: Phase Aménorrhée, Alerte de Danger.');
        $this->command->newLine();
    }

    private function seedOligomenorrheaLongScenario(int $athleteId): void
    {
        $this->command->info('  - Scénario: Oligoménorrhée (cycles longs)');
        // J1 sur des dates qui génèrent des cycles de 50 jours.
        // Dernier J1 il y a 35 jours (par exemple: 1er juin si aujourd'hui est le 6 juillet)
        $this->insertMetric($athleteId, 135); // J1 antérieur
        $this->insertMetric($athleteId, 85);  // J1 antérieur
        $this->insertMetric($athleteId, 35);  // Dernier J1
        $this->command->info('    -> Attendu: Phase Oligoménorrhée, Alerte de Danger (moy. ~50 jours).');
        $this->command->newLine();
    }

    private function seedOligomenorrheaShortScenario(int $athleteId): void
    {
        $this->command->info('  - Scenario: Oligoménorrhée (Cycles courts)');
        // Dates: J1 - 56 jours (2025-05-10), J1 - 38 jours (2025-05-28), J1 - 20 jours (2025-06-16)
        $this->insertMetric($athleteId, 56);
        $this->insertMetric($athleteId, 38);
        $this->insertMetric($athleteId, 20); // Dernière J1
        $this->command->info('    -> Attendu: Phase Oligoménorrhée, Alerte de Danger (moy. 18 jours).');
        $this->command->newLine();
    }

    private function seedPotentialDelayScenario(int $athleteId): void
    {
        $this->command->info('  - Scenario: Potentiel Retard (Nécessite modification de getAthleteAlerts)');
        // Dates: J1 - 92 jours (2025-04-05), J1 - 64 jours (2025-05-03), J1 - 34 jours (2025-06-02)
        $this->insertMetric($athleteId, 92);
        $this->insertMetric($athleteId, 64);
        $this->insertMetric($athleteId, 34); // Dernière J1
        $this->command->info("    -> Attendu: Phase Potentiel retard ou cycle long, Alerte d'Avertissement (moy. ~29 jours, 34 jours passés).");
        $this->command->newLine();
    }

    private function seedNormalCycleScenario(int $athleteId): void
    {
        $this->command->info('  - Scenario: Cycle Normal');
        // Dates: J1 - 87 jours (2025-04-10), J1 - 59 jours (2025-05-08), J1 - 30 jours (2025-06-06)
        $this->insertMetric($athleteId, 87);
        $this->insertMetric($athleteId, 59);
        $this->insertMetric($athleteId, 30); // Dernière J1
        $this->command->info('    -> Attendu: Phase Lutéale/Menstruelle, Alerte de Succès (moy. ~28.5 jours, 30 jours passés).');
        $this->command->newLine();
    }
}
